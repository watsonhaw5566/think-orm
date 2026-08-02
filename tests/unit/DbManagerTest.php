<?php

declare(strict_types=1);

namespace tests\unit;

use Closure;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use tests\TestCaseBase;
use think\db\Raw;
use think\DbManager;

#[Group('unit')]
#[AllowMockObjectsWithoutExpectations]
class DbManagerTest extends TestCaseBase
{
    private DbManager $dbManager;

    public function setUp(): void
    {
        parent::setUp();
        $this->dbManager = new DbManager();
    }

    #[Test]
    public function setAndGetConfigEmpty(): void
    {
        $this->dbManager->setConfig([]);
        $this->assertSame([], $this->dbManager->getConfig());
    }

    #[Test]
    public function setAndGetConfigAll(): void
    {
        $config = [
            'default'     => 'mysql',
            'connections' => [
                'mysql' => ['type' => 'mysql', 'hostname' => 'localhost'],
            ],
        ];
        $this->dbManager->setConfig($config);
        $this->assertSame($config, $this->dbManager->getConfig());
    }

    #[Test]
    public function getConfigByName(): void
    {
        $config = ['default' => 'mysql', 'auto_timestamp' => true];
        $this->dbManager->setConfig($config);
        $this->assertSame('mysql', $this->dbManager->getConfig('default'));
        $this->assertTrue($this->dbManager->getConfig('auto_timestamp'));
    }

    #[Test]
    public function getConfigNonExistentReturnsDefault(): void
    {
        $this->dbManager->setConfig(['default' => 'mysql']);
        $this->assertNull($this->dbManager->getConfig('nonexistent'));
        $this->assertSame('fallback', $this->dbManager->getConfig('nonexistent', 'fallback'));
    }

    #[Test]
    public function setCache(): void
    {
        $cache = $this->createMock(CacheInterface::class);
        $this->dbManager->setCache($cache);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function setLogWithLoggerInterface(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())
            ->method('log')
            ->with('sql', 'SELECT * FROM users');

        $this->dbManager->setLog($logger);
        $this->dbManager->log('SELECT * FROM users');
    }

    #[Test]
    public function setLogWithClosure(): void
    {
        $receivedType = null;
        $receivedLog  = null;

        $closure = function ($type, $log) use (&$receivedType, &$receivedLog) {
            $receivedType = $type;
            $receivedLog  = $log;
        };

        $this->dbManager->setLog($closure);
        $this->dbManager->log('INSERT INTO users', 'sql');

        $this->assertSame('sql', $receivedType);
        $this->assertSame('INSERT INTO users', $receivedLog);
    }

    #[Test]
    public function logWithNoLogger(): void
    {
        $this->dbManager->log('no logger set');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function getDbLogReturnsEmptyArray(): void
    {
        $this->assertSame([], $this->dbManager->getDbLog());
        $this->assertSame([], $this->dbManager->getDbLog(true));
    }

    #[Test]
    public function clearAndGetQueryTimes(): void
    {
        $this->assertSame(0, $this->dbManager->getQueryTimes());
        $this->dbManager->clearQueryTimes();
        $this->assertSame(0, $this->dbManager->getQueryTimes());
    }

    #[Test]
    public function updateQueryTimesNoop(): void
    {
        $this->dbManager->updateQueryTimes();
        $this->assertSame(0, $this->dbManager->getQueryTimes());
    }

    #[Test]
    public function listenAndGetListen(): void
    {
        $callback1 = function () {};
        $callback2 = function () {};

        $this->dbManager->listen($callback1);
        $this->dbManager->listen($callback2);

        $listeners = $this->dbManager->getListen();
        $this->assertCount(2, $listeners);
        $this->assertSame($callback1, $listeners[0]);
        $this->assertSame($callback2, $listeners[1]);
    }

    #[Test]
    public function getListenEmptyByDefault(): void
    {
        $this->assertSame([], $this->dbManager->getListen());
    }

    #[Test]
    public function getInstanceEmptyInitially(): void
    {
        $this->assertSame([], $this->dbManager->getInstance());
    }

    #[Test]
    public function rawCreatesRawObject(): void
    {
        $raw = $this->dbManager->raw('COUNT(*)');
        $this->assertInstanceOf(Raw::class, $raw);
        $this->assertSame('COUNT(*)', $raw->getValue());
        $this->assertSame([], $raw->getBind());
    }

    #[Test]
    public function rawCreatesRawObjectWithBindings(): void
    {
        $bind = [':id' => 1];
        $raw  = $this->dbManager->raw('id = :id', $bind);
        $this->assertInstanceOf(Raw::class, $raw);
        $this->assertSame('id = :id', $raw->getValue());
        $this->assertSame($bind, $raw->getBind());
    }

    #[Test]
    public function eventAndTrigger(): void
    {
        $triggered  = false;
        $receivedParam = null;

        $this->dbManager->event('before_insert', function ($param) use (&$triggered, &$receivedParam) {
            $triggered     = true;
            $receivedParam = $param;
        });

        $this->dbManager->trigger('before_insert', ['data' => 'test']);
        $this->assertTrue($triggered);
        $this->assertSame(['data' => 'test'], $receivedParam);
    }

    #[Test]
    public function triggerNonExistentEventNoop(): void
    {
        $this->dbManager->trigger('nonexistent_event');
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function triggerMultipleCallbacks(): void
    {
        $callCount = 0;
        $this->dbManager->event('multi', function () use (&$callCount) {
            $callCount++;
        });
        $this->dbManager->event('multi', function () use (&$callCount) {
            $callCount++;
        });
        $this->dbManager->event('multi', function () use (&$callCount) {
            $callCount++;
        });

        $this->dbManager->trigger('multi');
        $this->assertSame(3, $callCount);
    }

    #[Test]
    public function eventCallbackWithNoParams(): void
    {
        $called = false;
        $this->dbManager->event('test_event', function ($params = null) use (&$called) {
            $called = true;
            $this->assertNull($params);
        });
        $this->dbManager->trigger('test_event');
        $this->assertTrue($called);
    }

    #[Test]
    public function triggerSqlNoop(): void
    {
        $this->dbManager->triggerSql();
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function connectWithUndefinedConfigThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->dbManager->connect('undefined_connection');
    }

    #[Test]
    public function setLogWithCustomType(): void
    {
        $receivedType = null;
        $receivedLog  = null;

        $closure = function ($type, $log) use (&$receivedType, &$receivedLog) {
            $receivedType = $type;
            $receivedLog  = $log;
        };

        $this->dbManager->setLog($closure);
        $this->dbManager->log('custom message', 'custom_type');

        $this->assertSame('custom_type', $receivedType);
        $this->assertSame('custom message', $receivedLog);
    }

    #[Test]
    public function constructInitializesModelSupport(): void
    {
        $dbManager = new DbManager();
        $this->assertInstanceOf(DbManager::class, $dbManager);
    }
}