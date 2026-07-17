<?php

declare(strict_types=1);

namespace tests\Unit;

use PHPUnit\Framework\TestCase;
use think\db\Raw;
use Stringable;

/**
 * 单元测试示例 - 测试 Raw 类
 * 不需要数据库连接，可以独立运行
 */
class RawTest extends TestCase
{
    public function testGetValueWithString(): void
    {
        $raw = new Raw('COUNT(*)');
        $this->assertEquals('COUNT(*)', $raw->getValue());
    }

    public function testGetValueWithEmptyBind(): void
    {
        $raw = new Raw('SELECT 1');
        $this->assertIsArray($raw->getBind());
        $this->assertEmpty($raw->getBind());
    }

    public function testGetBindWithValues(): void
    {
        $bind = ['id' => 100, 'name' => 'test'];
        $raw  = new Raw('SELECT * FROM user WHERE id = :id', $bind);
        $this->assertEquals($bind, $raw->getBind());
    }

    public function testGetValueWithStringable(): void
    {
        $stringable = new class () implements Stringable {
            public function __toString(): string
            {
                return 'NOW()';
            }
        };

        $raw = new Raw($stringable);
        $this->assertIsString((string) $raw->getValue());
    }
}
