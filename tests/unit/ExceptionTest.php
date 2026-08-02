<?php

declare(strict_types=1);

namespace tests\unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\SimpleCache\InvalidArgumentException as SimpleCacheInvalidArgumentInterface;
use tests\TestCaseBase;
use think\db\exception\BindParamException;
use think\db\exception\DataNotFoundException;
use think\db\exception\DbEventException;
use think\db\exception\DbException;
use think\db\exception\InvalidArgumentException;
use think\db\exception\ModelEventException;
use think\db\exception\ModelNotFoundException;
use think\db\exception\PDOException;
use think\Exception;

#[Group('unit')]
class ExceptionTest extends TestCaseBase
{
    #[Test]
    public function thinkExceptionSetAndGetData(): void
    {
        $exception = new class ('test') extends Exception {
            public function addDebugData(string $label, array $data): void
            {
                $this->setData($label, $data);
            }
        };

        $exception->addDebugData('Debug Info', ['key' => 'value', 'count' => 42]);
        $data = $exception->getData();
        $this->assertArrayHasKey('Debug Info', $data);
        $this->assertSame(['key' => 'value', 'count' => 42], $data['Debug Info']);
    }

    #[Test]
    public function thinkExceptionGetDataEmptyByDefault(): void
    {
        $exception = new Exception('base exception');
        $this->assertSame([], $exception->getData());
    }

    #[Test]
    public function thinkExceptionMessageAndCode(): void
    {
        $exception = new Exception('custom message', 1234);
        $this->assertSame('custom message', $exception->getMessage());
        $this->assertSame(1234, $exception->getCode());
    }

    #[Test]
    public function invalidArgumentExceptionExtendsCoreAndImplementsInterface(): void
    {
        $exception = new InvalidArgumentException('invalid arg');
        $this->assertInstanceOf(\InvalidArgumentException::class, $exception);
        $this->assertInstanceOf(SimpleCacheInvalidArgumentInterface::class, $exception);
        $this->assertSame('invalid arg', $exception->getMessage());
    }

    #[Test]
    public function dbExceptionStoresDataCorrectly(): void
    {
        $config = [
            'type'     => 'mysql',
            'hostname' => 'localhost',
            'database' => 'test_db',
            'username' => 'root',
            'password' => 'secret',
        ];
        $sql       = 'SELECT * FROM users WHERE id = 1';
        $exception = new DbException('db error', $config, $sql, 10500);

        $this->assertSame('db error', $exception->getMessage());
        $this->assertSame(10500, $exception->getCode());
        $this->assertInstanceOf(Exception::class, $exception);

        $data = $exception->getData();
        $this->assertArrayHasKey('Database Status', $data);
        $this->assertSame(10500, $data['Database Status']['Error Code']);
        $this->assertSame('db error', $data['Database Status']['Error Message']);
        $this->assertSame($sql, $data['Database Status']['Error SQL']);

        $this->assertArrayHasKey('Database Config', $data);
        $this->assertArrayNotHasKey('username', $data['Database Config']);
        $this->assertArrayNotHasKey('password', $data['Database Config']);
        $this->assertSame('mysql', $data['Database Config']['type']);
        $this->assertSame('test_db', $data['Database Config']['database']);
    }

    #[Test]
    public function dbExceptionWithEmptyConfigAndSql(): void
    {
        $exception = new DbException('simple error');
        $data      = $exception->getData();
        $this->assertSame('', $data['Database Status']['Error SQL']);
        $this->assertSame([], $data['Database Config']);
    }

    #[Test]
    public function dataNotFoundExceptionGetTable(): void
    {
        $exception = new DataNotFoundException('no data', 'test_users', ['type' => 'mysql']);
        $this->assertSame('test_users', $exception->getTable());
        $this->assertSame('no data', $exception->getMessage());
        $this->assertInstanceOf(DbException::class, $exception);
    }

    #[Test]
    public function dataNotFoundExceptionEmptyTable(): void
    {
        $exception = new DataNotFoundException('not found');
        $this->assertSame('', $exception->getTable());
    }

    #[Test]
    public function modelNotFoundExceptionGetModel(): void
    {
        $exception = new ModelNotFoundException('model not found', 'app\\model\\User', ['type' => 'mysql']);
        $this->assertSame('app\\model\\User', $exception->getModel());
        $this->assertSame('model not found', $exception->getMessage());
        $this->assertInstanceOf(DbException::class, $exception);
    }

    #[Test]
    public function modelNotFoundExceptionEmptyModel(): void
    {
        $exception = new ModelNotFoundException('no model');
        $this->assertSame('', $exception->getModel());
    }

    #[Test]
    public function bindParamExceptionStoresBindData(): void
    {
        $config    = ['type' => 'mysql'];
        $sql       = 'INSERT INTO users VALUES (?, ?, ?)';
        $bind      = ['id' => 1, 'name' => 'test', 'email' => 'test@example.com'];
        $exception = new BindParamException('bind error', $config, $sql, $bind, 10502);

        $this->assertSame(10502, $exception->getCode());
        $data = $exception->getData();
        $this->assertArrayHasKey('Bind Param', $data);
        $this->assertSame($bind, $data['Bind Param']);
        $this->assertInstanceOf(DbException::class, $exception);
    }

    #[Test]
    public function dbEventExceptionExtendsDbException(): void
    {
        $exception = new DbEventException('event error');
        $this->assertInstanceOf(DbException::class, $exception);
        $this->assertSame('event error', $exception->getMessage());
    }

    #[Test]
    public function modelEventExceptionExtendsDbException(): void
    {
        $exception = new ModelEventException('model event error');
        $this->assertInstanceOf(DbException::class, $exception);
        $this->assertSame('model event error', $exception->getMessage());
    }

    #[Test]
    public function pdoExceptionWrapsNativePdoException(): void
    {
        $nativePdoException            = new \PDOException('SQLSTATE[42S02]: Base table or view not found');
        $nativePdoException->errorInfo = ['42S02', 1146, "Table 'test.nonexistent' doesn't exist"];

        $config    = ['type' => 'mysql', 'database' => 'test'];
        $sql       = 'SELECT * FROM nonexistent';
        $exception = new PDOException($nativePdoException, $config, $sql, 10501);

        $this->assertSame(10501, $exception->getCode());
        $this->assertInstanceOf(DbException::class, $exception);

        $data = $exception->getData();
        $this->assertArrayHasKey('PDO Error Info', $data);
        $this->assertSame('42S02', $data['PDO Error Info']['SQLSTATE']);
        $this->assertSame(1146, $data['PDO Error Info']['Driver Error Code']);
        $this->assertSame("Table 'test.nonexistent' doesn't exist", $data['PDO Error Info']['Driver Error Message']);
    }

    #[Test]
    public function pdoExceptionWithEmptyErrorInfo(): void
    {
        $nativePdoException = new \PDOException('simple pdo error');
        $exception          = new PDOException($nativePdoException);
        $this->assertSame('simple pdo error', $exception->getMessage());
        $this->assertNotEmpty($exception->getData());
    }

    #[Test]
    public function exceptionHierarchy(): void
    {
        $this->assertTrue(is_subclass_of(DbException::class, Exception::class));
        $this->assertTrue(is_subclass_of(DataNotFoundException::class, DbException::class));
        $this->assertTrue(is_subclass_of(ModelNotFoundException::class, DbException::class));
        $this->assertTrue(is_subclass_of(BindParamException::class, DbException::class));
        $this->assertTrue(is_subclass_of(DbEventException::class, DbException::class));
        $this->assertTrue(is_subclass_of(ModelEventException::class, DbException::class));
        $this->assertTrue(is_subclass_of(PDOException::class, DbException::class));
    }
}
