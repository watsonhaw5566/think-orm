<?php

declare(strict_types=1);

namespace tests\unit;

use ArrayAccess;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use tests\TestCaseBase;
use think\db\Raw;
use think\db\Where;

#[Group('unit')]
class WhereTest extends TestCaseBase
{
    #[Test]
    public function implementsArrayAccess(): void
    {
        $where = new Where();
        $this->assertInstanceOf(ArrayAccess::class, $where);
    }

    #[Test]
    public function constructWithEmptyWhere(): void
    {
        $where = new Where();
        $this->assertSame([], $where->parse());
    }

    #[Test]
    public function constructWithWhereArray(): void
    {
        $where = new Where(['id' => 1, 'name' => 'test']);
        $parsed = $where->parse();
        $this->assertCount(2, $parsed);
        $this->assertSame(['id', '=', 1], $parsed[0]);
        $this->assertSame(['name', '=', 'test'], $parsed[1]);
    }

    #[Test]
    public function constructWithEnclose(): void
    {
        $where = new Where(['id' => 1, 'status' => 2], true);
        $parsed = $where->parse();
        $this->assertCount(1, $parsed);
        $this->assertIsArray($parsed[0]);
        $this->assertCount(2, $parsed[0]);
    }

    #[Test]
    public function encloseMethodReturnsSelf(): void
    {
        $where = new Where();
        $result = $where->enclose(true);
        $this->assertSame($where, $result);
    }

    #[Test]
    public function encloseMethodTogglesEnclosure(): void
    {
        $where = new Where(['id' => 1]);
        $this->assertCount(1, $where->parse());
        $this->assertSame(['id', '=', 1], $where->parse()[0]);

        $where->enclose(true);
        $parsed = $where->parse();
        $this->assertCount(1, $parsed);
        $this->assertIsArray($parsed[0]);

        $where->enclose(false);
        $this->assertSame(['id', '=', 1], $where->parse()[0]);
    }

    #[Test]
    public function parseWithNullValue(): void
    {
        $where = new Where(['deleted_at' => null]);
        $parsed = $where->parse();
        $this->assertSame(['deleted_at', 'NULL', ''], $parsed[0]);
    }

    #[Test]
    public function parseWithRawValue(): void
    {
        $raw = new Raw('status = 1');
        $where = new Where(['field' => $raw]);
        $parsed = $where->parse();
        $this->assertSame(['field', 'exp', $raw], $parsed[0]);
    }

    #[Test]
    public function parseWithArrayOperator(): void
    {
        $where = new Where(['id' => ['>', 10]]);
        $parsed = $where->parse();
        $this->assertSame(['id', '>', 10], $parsed[0]);
    }

    #[Test]
    public function parseWithInCondition(): void
    {
        $where = new Where(['id' => ['IN', [1, 2, 3]]]);
        $parsed = $where->parse();
        $this->assertSame(['id', 'IN', [1, 2, 3]], $parsed[0]);
    }

    #[Test]
    public function parseWithBetweenCondition(): void
    {
        $where = new Where(['age' => ['BETWEEN', [18, 60]]]);
        $parsed = $where->parse();
        $this->assertSame(['age', 'BETWEEN', [18, 60]], $parsed[0]);
    }

    #[Test]
    public function parseWithLikeCondition(): void
    {
        $where = new Where(['name' => ['LIKE', '%test%']]);
        $parsed = $where->parse();
        $this->assertSame(['name', 'LIKE', '%test%'], $parsed[0]);
    }

    #[Test]
    public function parseItemNullString(): void
    {
        $where = new Where(['deleted_at' => ['NULL']]);
        $parsed = $where->parse();
        $this->assertSame(['deleted_at', 'NULL', ''], $parsed[0]);
    }

    #[Test]
    public function parseItemNotNullString(): void
    {
        $where = new Where(['deleted_at' => ['NOTNULL']]);
        $parsed = $where->parse();
        $this->assertSame(['deleted_at', 'NOTNULL', ''], $parsed[0]);
    }

    #[Test]
    public function parseItemNotNullWithSpaceString(): void
    {
        $where = new Where(['deleted_at' => ['NOT NULL']]);
        $parsed = $where->parse();
        $this->assertSame(['deleted_at', 'NOT NULL', ''], $parsed[0]);
    }

    #[Test]
    public function parseItemNullEqualOperator(): void
    {
        $where = new Where(['field' => ['=']]);
        $parsed = $where->parse();
        $this->assertSame(['field', 'NULL', ''], $parsed[0]);
    }

    #[Test]
    public function parseItemNullOperator(): void
    {
        $where = new Where(['field' => [null]]);
        $parsed = $where->parse();
        $this->assertSame(['field', 'NULL', ''], $parsed[0]);
    }

    #[Test]
    public function parseItemNotEqualOperator(): void
    {
        $where = new Where(['field' => ['<>']]);
        $parsed = $where->parse();
        $this->assertSame(['field', 'NOTNULL', ''], $parsed[0]);
    }

    #[Test]
    public function parseItemSingleValueAsEqual(): void
    {
        $where = new Where(['status' => [1]]);
        $parsed = $where->parse();
        $this->assertSame(['status', '=', 1], $parsed[0]);
    }

    #[Test]
    public function magicMethodsSetGet(): void
    {
        $where = new Where();
        $where->id = 5;
        $this->assertSame(5, $where->id);
    }

    #[Test]
    public function magicMethodGetNonExistentReturnsNull(): void
    {
        $where = new Where();
        $this->assertNull($where->nonexistent);
    }

    #[Test]
    public function magicMethodIsset(): void
    {
        $where = new Where();
        $this->assertFalse(isset($where->id));
        $where->id = 1;
        $this->assertTrue(isset($where->id));
    }

    #[Test]
    public function magicMethodUnset(): void
    {
        $where = new Where();
        $where->id = 1;
        $this->assertTrue(isset($where->id));
        unset($where->id);
        $this->assertFalse(isset($where->id));
    }

    #[Test]
    public function arrayAccessOffsetSet(): void
    {
        $where = new Where();
        $where['id'] = 10;
        $this->assertSame(10, $where['id']);
    }

    #[Test]
    public function arrayAccessOffsetExists(): void
    {
        $where = new Where();
        $this->assertFalse(isset($where['id']));
        $where['id'] = 10;
        $this->assertTrue(isset($where['id']));
    }

    #[Test]
    public function arrayAccessOffsetUnset(): void
    {
        $where = new Where();
        $where['id'] = 10;
        $this->assertTrue(isset($where['id']));
        unset($where['id']);
        $this->assertFalse(isset($where['id']));
    }

    #[Test]
    public function arrayAccessOffsetGetNonExistent(): void
    {
        $where = new Where();
        $this->assertNull($where['nonexistent']);
    }

    #[Test]
    public function parseMultipleConditions(): void
    {
        $where = new Where([
            'id' => ['>=', 10],
            'status' => 1,
            'name' => ['LIKE', '%john%'],
            'deleted_at' => null,
        ]);
        $parsed = $where->parse();
        $this->assertCount(4, $parsed);
        $this->assertSame(['id', '>=', 10], $parsed[0]);
        $this->assertSame(['status', '=', 1], $parsed[1]);
        $this->assertSame(['name', 'LIKE', '%john%'], $parsed[2]);
        $this->assertSame(['deleted_at', 'NULL', ''], $parsed[3]);
    }

    #[Test]
    public function parseSameFieldMultipleConditions(): void
    {
        $where = new Where([
            'price' => [['>=', 100], ['<=', 500]],
        ]);
        $parsed = $where->parse();
        $this->assertSame(['price', ['>=', 100], ['<=', 500]], $parsed[0]);
    }

    #[Test]
    public function encloseWithEmptyWhere(): void
    {
        $where = new Where([], true);
        $parsed = $where->parse();
        $this->assertSame([[]], $parsed);
    }

    #[Test]
    public function parseWithVariousOperators(): void
    {
        $operators = [
            ['>', 10],
            ['<', 20],
            ['>=', 30],
            ['<=', 40],
            ['<>', 50],
            ['!=', 60],
            ['=~', 'regex'],
        ];

        foreach ($operators as [$op, $val]) {
            $where = new Where(['field' => [$op, $val]]);
            $parsed = $where->parse();
            $this->assertSame(['field', $op, $val], $parsed[0]);
        }
    }
}