<?php

declare(strict_types=1);

namespace tests\unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Stringable;
use tests\TestCaseBase;
use think\db\Raw;
use ReflectionClass;

#[Group('unit')]
class RawTest extends TestCaseBase
{
    #[Test]
    public function constructWithValueOnly(): void
    {
        $raw = new Raw('COUNT(*)');
        $this->assertSame('COUNT(*)', $raw->getValue());
        $this->assertSame([], $raw->getBind());
    }

    #[Test]
    public function constructWithValueAndBind(): void
    {
        $bind = [':id' => 1, ':name' => 'test'];
        $raw  = new Raw('id = :id AND name = :name', $bind);
        $this->assertSame('id = :id AND name = :name', $raw->getValue());
        $this->assertSame($bind, $raw->getBind());
    }

    #[Test]
    public function constructWithStringableValue(): void
    {
        $stringable = new class () implements Stringable {
            public function __toString(): string
            {
                return 'NOW()';
            }
        };

        $raw         = new Raw($stringable);
        $reflection  = new ReflectionClass($raw);
        $valueProp   = $reflection->getProperty('value');
        $storedValue = $valueProp->getValue($raw);
        $this->assertSame('NOW()', (string) $storedValue);
        $this->assertSame([], $raw->getBind());
    }

    #[Test]
    public function getValueReturnsOriginalValue(): void
    {
        $sql = 'SELECT * FROM users WHERE status = 1';
        $raw = new Raw($sql);
        $this->assertSame($sql, $raw->getValue());
    }

    #[Test]
    public function getBindReturnsOriginalBindings(): void
    {
        $bind = [':status' => 1, ':role' => 'admin'];
        $raw  = new Raw('status = :status AND role = :role', $bind);
        $this->assertSame($bind, $raw->getBind());
    }

    #[Test]
    public function emptyBindingsArray(): void
    {
        $raw = new Raw('1=1', []);
        $this->assertSame([], $raw->getBind());
    }

    #[Test]
    public function mixedBindingsTypes(): void
    {
        $bind = [':int' => 42, ':string' => 'hello', ':float' => 3.14, ':null' => null];
        $raw  = new Raw('test', $bind);
        $this->assertSame($bind, $raw->getBind());
    }
}
