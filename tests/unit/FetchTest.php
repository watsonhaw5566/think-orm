<?php

declare(strict_types=1);

namespace tests\unit;

use Mockery;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use tests\TestCaseBase;
use think\db\BaseQuery;
use think\db\Builder;
use think\db\Connection;
use think\db\Fetch;

#[Group('unit')]
class FetchTest extends TestCaseBase
{
    public function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    #[Test]
    public function constructWithQuery(): void
    {
        $query = Mockery::mock(BaseQuery::class);
        $builder = Mockery::mock(Builder::class);
        $connection = Mockery::mock(Connection::class);

        $query->shouldReceive('getConnection')->once()->andReturn($connection);
        $connection->shouldReceive('getBuilder')->once()->andReturn($builder);

        $fetch = new Fetch($query);
        $this->assertInstanceOf(Fetch::class, $fetch);
    }

    #[Test]
    public function fetchSqlWithBindings(): void
    {
        $query = Mockery::mock(BaseQuery::class);
        $builder = Mockery::mock(Builder::class);
        $connection = Mockery::mock(Connection::class);

        $query->shouldReceive('getConnection')->andReturn($connection);
        $connection->shouldReceive('getBuilder')->andReturn($builder);

        $query->shouldReceive('getBind')->once()->andReturn([1]);
        $connection->shouldReceive('getRealSql')->once()->with('SELECT * FROM users WHERE id = ?', [1])->andReturn("SELECT * FROM users WHERE id = 1");

        $fetch = new Fetch($query);
        $reflection = new \ReflectionClass($fetch);
        $method = $reflection->getMethod('fetch');
        $method->setAccessible(true);

        $result = $method->invoke($fetch, 'SELECT * FROM users WHERE id = ?');
        $this->assertSame("SELECT * FROM users WHERE id = 1", $result);
    }

    #[Test]
    public function insertGetIdDelegatesToInsert(): void
    {
        $query = Mockery::mock(BaseQuery::class);
        $builder = Mockery::mock(Builder::class);
        $connection = Mockery::mock(Connection::class);

        $query->shouldReceive('getConnection')->andReturn($connection);
        $connection->shouldReceive('getBuilder')->andReturn($builder);

        $data = ['name' => 'test', 'email' => 'test@example.com'];

        $query->shouldReceive('parseOptions')->once()->andReturn([]);
        $query->shouldReceive('setOption')->once()->with('data', $data);
        $builder->shouldReceive('insert')->once()->with($query)->andReturn('INSERT INTO users (name, email) VALUES (?, ?)');
        $query->shouldReceive('getBind')->once()->andReturn(['test', 'test@example.com']);
        $connection->shouldReceive('getRealSql')->once()->andReturn("INSERT INTO users (name, email) VALUES ('test', 'test@example.com')");

        $fetch = new Fetch($query);
        $result = $fetch->insertGetId($data);
        $this->assertSame("INSERT INTO users (name, email) VALUES ('test', 'test@example.com')", $result);
    }

    #[Test]
    public function selectOrFailWithEmptyArrayDelegatesToSelect(): void
    {
        $query = Mockery::mock(BaseQuery::class);
        $builder = Mockery::mock(Builder::class);
        $connection = Mockery::mock(Connection::class);

        $query->shouldReceive('getConnection')->andReturn($connection);
        $connection->shouldReceive('getBuilder')->andReturn($builder);

        $query->shouldReceive('parseOptions')->once()->andReturn([]);
        $builder->shouldReceive('select')->once()->with($query)->andReturn('SELECT * FROM users');
        $query->shouldReceive('getBind')->once()->andReturn([]);
        $connection->shouldReceive('getRealSql')->once()->with('SELECT * FROM users', [])->andReturn('SELECT * FROM users');

        $fetch = new Fetch($query);
        $result = $fetch->selectOrFail([]);
        $this->assertSame('SELECT * FROM users', $result);
    }

    #[Test]
    public function findOrFailDelegatesToFind(): void
    {
        $query = Mockery::mock(BaseQuery::class);
        $builder = Mockery::mock(Builder::class);
        $connection = Mockery::mock(Connection::class);

        $query->shouldReceive('getConnection')->andReturn($connection);
        $connection->shouldReceive('getBuilder')->andReturn($builder);

        $query->shouldReceive('parseOptions')->once()->andReturn([]);
        $builder->shouldReceive('select')->once()->with($query, true)->andReturn('SELECT * FROM users LIMIT 1');
        $query->shouldReceive('getBind')->once()->andReturn([]);
        $connection->shouldReceive('getRealSql')->once()->with('SELECT * FROM users LIMIT 1', [])->andReturn('SELECT * FROM users LIMIT 1');

        $fetch = new Fetch($query);
        $result = $fetch->findOrFail();
        $this->assertSame('SELECT * FROM users LIMIT 1', $result);
    }

    #[Test]
    public function findOrEmptyDelegatesToFindNoArgs(): void
    {
        $query = Mockery::mock(BaseQuery::class);
        $builder = Mockery::mock(Builder::class);
        $connection = Mockery::mock(Connection::class);

        $query->shouldReceive('getConnection')->andReturn($connection);
        $connection->shouldReceive('getBuilder')->andReturn($builder);

        $query->shouldReceive('parseOptions')->once()->andReturn([]);
        $builder->shouldReceive('select')->once()->with($query, true)->andReturn('SELECT * FROM users LIMIT 1');
        $query->shouldReceive('getBind')->once()->andReturn([]);
        $connection->shouldReceive('getRealSql')->once()->with('SELECT * FROM users LIMIT 1', [])->andReturn('SELECT * FROM users LIMIT 1');

        $fetch = new Fetch($query);
        $result = $fetch->findOrEmpty();
        $this->assertSame('SELECT * FROM users LIMIT 1', $result);
    }

    #[Test]
    public function aggregateBuildsCorrectSql(): void
    {
        $query = Mockery::mock(BaseQuery::class);
        $builder = Mockery::mock(Builder::class);
        $connection = Mockery::mock(Connection::class);

        $query->shouldReceive('getConnection')->andReturn($connection);
        $connection->shouldReceive('getBuilder')->andReturn($builder);

        $query->shouldReceive('parseOptions')->andReturn([]);
        $builder->shouldReceive('parseKey')->once()->with($query, 'amount')->andReturn('`amount`');

        $fetch = new Fetch($query);
        $reflection = new \ReflectionClass($fetch);

        $query->shouldReceive('removeOption')->andReturnSelf();
        $query->shouldReceive('setOption')->once()->with('field', ['SUM(`amount`) AS think_sum']);
        $builder->shouldReceive('select')->once()->with($query, false)->andReturn('SELECT SUM(`amount`) AS think_sum');
        $query->shouldReceive('getBind')->once()->andReturn([]);
        $connection->shouldReceive('getRealSql')->once()->andReturn('SELECT SUM(`amount`) AS think_sum');

        $aggregateMethod = $reflection->getMethod('aggregate');

        $result = $aggregateMethod->invoke($fetch, 'SUM', 'amount');
        $this->assertSame('SELECT SUM(`amount`) AS think_sum', $result);
    }

    #[Test]
    public function insertAllWithoutLimit(): void
    {
        $query = Mockery::mock(BaseQuery::class);
        $builder = Mockery::mock(Builder::class);
        $connection = Mockery::mock(Connection::class);

        $query->shouldReceive('getConnection')->andReturn($connection);
        $connection->shouldReceive('getBuilder')->andReturn($builder);

        $dataSet = [
            ['name' => 'a'],
            ['name' => 'b'],
        ];

        $query->shouldReceive('parseOptions')->once()->andReturn([]);
        $builder->shouldReceive('insertAll')->once()->with($query, $dataSet)->andReturn('INSERT INTO users (name) VALUES (?), (?)');
        $query->shouldReceive('getBind')->once()->andReturn(['a', 'b']);
        $connection->shouldReceive('getRealSql')->once()->andReturn("INSERT INTO users (name) VALUES ('a'), ('b')");

        $fetch = new Fetch($query);
        $result = $fetch->insertAll($dataSet);
        $this->assertSame("INSERT INTO users (name) VALUES ('a'), ('b')", $result);
    }
}