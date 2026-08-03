<?php

declare(strict_types=1);

namespace tests\unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use tests\TestCaseBase;
use think\db\exception\OptimLockException;
use think\Exception;

#[Group('unit')]
class OptimLockExceptionTest extends TestCaseBase
{
    #[Test]
    public function optimLockExceptionDefaultValues(): void
    {
        $exception = new OptimLockException();

        $this->assertInstanceOf(Exception::class, $exception);
        $this->assertSame('The record has been updated by another process', $exception->getMessage());
        $this->assertSame(10600, $exception->getCode());
        $this->assertSame('', $exception->getLockField());
        $this->assertNull($exception->getExpectedVersion());
        $this->assertSame(0, $exception->getAffectedRows());
    }

    #[Test]
    public function optimLockExceptionCustomValues(): void
    {
        $exception = new OptimLockException(
            'Custom conflict message',
            'version',
            5,
            0,
            10601
        );

        $this->assertSame('Custom conflict message', $exception->getMessage());
        $this->assertSame(10601, $exception->getCode());
        $this->assertSame('version', $exception->getLockField());
        $this->assertSame(5, $exception->getExpectedVersion());
        $this->assertSame(0, $exception->getAffectedRows());
    }

    #[Test]
    public function optimLockExceptionStoresDebugData(): void
    {
        $exception = new OptimLockException(
            'Update conflict',
            'lock_version',
            3,
            0
        );

        $data = $exception->getData();
        $this->assertArrayHasKey('Optimistic Lock', $data);
        $this->assertSame('lock_version', $data['Optimistic Lock']['Lock Field']);
        $this->assertSame(3, $data['Optimistic Lock']['Expected Version']);
        $this->assertSame(0, $data['Optimistic Lock']['Affected Rows']);
    }

    #[Test]
    public function optimLockExceptionExtendsThinkException(): void
    {
        $exception = new OptimLockException();
        $this->assertInstanceOf(Exception::class, $exception);
    }

    #[Test]
    public function optimLockExceptionWithAffectedRows(): void
    {
        $exception = new OptimLockException(
            'Conflict detected',
            'row_version',
            10,
            0
        );

        $this->assertSame(0, $exception->getAffectedRows());
        $this->assertSame('row_version', $exception->getLockField());
        $this->assertSame(10, $exception->getExpectedVersion());
    }
}
