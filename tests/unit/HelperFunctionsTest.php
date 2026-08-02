<?php

declare(strict_types=1);

namespace tests\unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use tests\TestCaseBase;

use function tests\array_column_ex;
use function tests\array_value_sort;

#[Group('unit')]
class HelperFunctionsTest extends TestCaseBase
{
    #[Test]
    public function arrayColumnExSimpleColumns(): void
    {
        $input = [
            ['id' => 1, 'name' => 'Alice', 'age' => 25],
            ['id' => 2, 'name' => 'Bob', 'age' => 30],
        ];
        $result = array_column_ex($input, ['id', 'name']);

        $this->assertCount(2, $result);
        $this->assertSame(['id' => 1, 'name' => 'Alice'], $result[0]);
        $this->assertSame(['id' => 2, 'name' => 'Bob'], $result[1]);
    }

    #[Test]
    public function arrayColumnExWithColumnMapping(): void
    {
        $input = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];
        $result = array_column_ex($input, ['id' => 'user_id', 'name' => 'user_name']);

        $this->assertCount(2, $result);
        $this->assertSame(['user_id' => 1, 'user_name' => 'Alice'], $result[0]);
        $this->assertSame(['user_id' => 2, 'user_name' => 'Bob'], $result[1]);
    }

    #[Test]
    public function arrayColumnExWithCallable(): void
    {
        $input = [
            ['id' => 1, 'name' => 'alice'],
            ['id' => 2, 'name' => 'bob'],
        ];
        $result = array_column_ex($input, [
            'id',
            'upper_name' => fn ($row) => strtoupper($row['name']),
        ]);

        $this->assertCount(2, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertSame('ALICE', $result[0]['upper_name']);
        $this->assertSame(2, $result[1]['id']);
        $this->assertSame('BOB', $result[1]['upper_name']);
    }

    #[Test]
    public function arrayColumnExWithKey(): void
    {
        $input = [
            ['id' => 1, 'name' => 'Alice'],
            ['id' => 2, 'name' => 'Bob'],
        ];
        $result = array_column_ex($input, ['id', 'name'], 'id');

        $this->assertCount(2, $result);
        $this->assertArrayHasKey(1, $result);
        $this->assertArrayHasKey(2, $result);
        $this->assertSame(['id' => 1, 'name' => 'Alice'], $result[1]);
        $this->assertSame(['id' => 2, 'name' => 'Bob'], $result[2]);
    }

    #[Test]
    public function arrayColumnExEmptyInput(): void
    {
        $result = array_column_ex([], ['id', 'name']);
        $this->assertSame([], $result);
    }

    #[Test]
    public function arrayColumnExMixedColumnTypes(): void
    {
        $input = [
            ['id' => 10, 'first' => 'John', 'last' => 'Doe'],
        ];
        $result = array_column_ex($input, [
            'id',
            'full_name' => fn ($row) => $row['first'] . ' ' . $row['last'],
            'last'      => 'family_name',
        ]);

        $this->assertCount(1, $result);
        $this->assertSame(10, $result[0]['id']);
        $this->assertSame('John Doe', $result[0]['full_name']);
        $this->assertSame('Doe', $result[0]['family_name']);
    }

    #[Test]
    public function arrayValueSortDoesNotError(): void
    {
        $input = [
            'a' => [3, 1, 2],
            'b' => ['c', 'a', 'b'],
        ];
        array_value_sort($input);
        $this->addToAssertionCount(1);
    }

    #[Test]
    public function arrayValueSortEmpty(): void
    {
        $input = [];
        array_value_sort($input);
        $this->assertSame([], $input);
    }

    #[Test]
    public function arrayValueSortEmptyInnerArrays(): void
    {
        $input = [
            'a' => [],
            'b' => [1],
        ];
        array_value_sort($input);
        $this->assertSame([], $input['a']);
        $this->assertSame([1], $input['b']);
    }

    #[Test]
    public function arrayValueSortDoesNotModifyOuter(): void
    {
        $input = [
            'a' => [3, 1, 2],
        ];
        $original = $input['a'];
        array_value_sort($input);
        $this->assertSame([3, 1, 2], $original);
    }
}
