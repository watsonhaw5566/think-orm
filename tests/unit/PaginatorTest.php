<?php

declare(strict_types=1);

namespace tests\unit;

use ArrayAccess;
use Countable;
use DomainException;
use IteratorAggregate;
use JsonSerializable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use tests\TestCaseBase;
use think\Collection;
use think\paginator\driver\Bootstrap;
use think\Paginator;

#[Group('unit')]
#[AllowMockObjectsWithoutExpectations]
class PaginatorTest extends TestCaseBase
{
    public function setUp(): void
    {
        parent::setUp();
        $reflection = new \ReflectionClass(Paginator::class);

        $currentPageResolver = $reflection->getProperty('currentPageResolver');
        $currentPageResolver->setValue(null, null);

        $currentPathResolver = $reflection->getProperty('currentPathResolver');
        $currentPathResolver->setValue(null, null);

        $maker = $reflection->getProperty('maker');

    }

    #[Test]
    public function paginatorImplementsInterfaces(): void
    {
        $paginator = new Bootstrap([1, 2, 3], 10, 1, 100);
        $this->assertInstanceOf(ArrayAccess::class, $paginator);
        $this->assertInstanceOf(Countable::class, $paginator);
        $this->assertInstanceOf(IteratorAggregate::class, $paginator);
        $this->assertInstanceOf(JsonSerializable::class, $paginator);
    }

    #[Test]
    public function constructNormalMode(): void
    {
        $items = array_fill(0, 5, 'item');
        $paginator = new Bootstrap($items, 10, 1, 100);

        $this->assertSame(1, $paginator->currentPage());
        $this->assertSame(10, $paginator->listRows());
        $this->assertSame(100, $paginator->total());
        $this->assertSame(10, $paginator->lastPage());
        $this->assertTrue($this->getHasMore($paginator));
        $this->assertFalse($paginator->isEmpty());
    }

    #[Test]
    public function constructSimpleMode(): void
    {
        $items = array_fill(0, 15, 'item');
        $paginator = new Bootstrap($items, 10, 1, null, true);

        $this->assertSame(1, $paginator->currentPage());
        $this->assertSame(10, $paginator->listRows());
        $this->assertTrue($this->getHasMore($paginator));
        $this->assertSame(10, $paginator->count());
    }

    #[Test]
    public function constructSimpleModeNoMorePages(): void
    {
        $items = array_fill(0, 5, 'item');
        $paginator = new Bootstrap($items, 10, 1, null, true);
        $this->assertFalse($this->getHasMore($paginator));
    }

    #[Test]
    public function totalInSimpleModeThrowsException(): void
    {
        $paginator = new Bootstrap([1, 2, 3], 10, 1, null, true);
        $this->expectException(DomainException::class);
        $paginator->total();
    }

    #[Test]
    public function lastPageInSimpleModeThrowsException(): void
    {
        $paginator = new Bootstrap([1, 2, 3], 10, 1, null, true);
        $this->expectException(DomainException::class);
        $paginator->lastPage();
    }

    #[Test]
    public function currentPageClampedToLastPage(): void
    {
        $items = array_fill(0, 5, 'item');
        $paginator = new Bootstrap($items, 10, 50, 100);
        $this->assertSame(10, $paginator->currentPage());
    }

    #[Test]
    public function currentPageClampedWhenZeroTotal(): void
    {
        $paginator = new Bootstrap([], 10, 5, 0);
        $this->assertSame(1, $paginator->currentPage());
    }

    #[Test]
    public function hasPages(): void
    {
        $p1 = new Bootstrap([1], 10, 1, 5);
        $this->assertFalse($p1->hasPages());

        $p2 = new Bootstrap([1], 10, 1, 100);
        $this->assertTrue($p2->hasPages());

        $p3 = new Bootstrap([1], 10, 5, 100);
        $this->assertTrue($p3->hasPages());
    }

    #[Test]
    public function hasPagesSimpleMode(): void
    {
        $items = array_fill(0, 5, 'item');
        $p1 = new Bootstrap($items, 10, 1, null, true);
        $this->assertFalse($p1->hasPages());

        $itemsMore = array_fill(0, 15, 'item');
        $p2 = new Bootstrap($itemsMore, 10, 1, null, true);
        $this->assertTrue($p2->hasPages());
    }

    #[Test]
    public function getUrlRange(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100);
        $urls = $paginator->getUrlRange(1, 3);

        $this->assertCount(3, $urls);
        $this->assertSame('/?page=1', $urls[1]);
        $this->assertSame('/?page=2', $urls[2]);
        $this->assertSame('/?page=3', $urls[3]);
    }

    #[Test]
    public function urlWithPageNumber(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('url');

        $this->assertSame('/?page=1', $method->invoke($paginator, 1));
        $this->assertSame('/?page=5', $method->invoke($paginator, 5));
    }

    #[Test]
    public function urlPageLessThanOrEqualToZeroBecomesOne(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('url');

        $this->assertSame('/?page=1', $method->invoke($paginator, 0));
        $this->assertSame('/?page=1', $method->invoke($paginator, -5));
    }

    #[Test]
    public function urlWithCustomPath(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100, false, ['path' => '/custom/path/']);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('url');

        $this->assertSame('/custom/path?page=1', $method->invoke($paginator, 1));
    }

    #[Test]
    public function urlWithPagePlaceholder(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100, false, ['path' => '/list/[PAGE]']);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('url');

        $this->assertSame('/list/1', $method->invoke($paginator, 1));
        $this->assertSame('/list/5', $method->invoke($paginator, 5));
    }

    #[Test]
    public function urlWithQueryParameters(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100, false, [
            'path'  => '/',
            'query' => ['sort' => 'name', 'order' => 'asc'],
        ]);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('url');

        $result = $method->invoke($paginator, 2);
        $this->assertStringContainsString('sort=name', $result);
        $this->assertStringContainsString('order=asc', $result);
        $this->assertStringContainsString('page=2', $result);
    }

    #[Test]
    public function fragment(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100);
        $result = $paginator->fragment('section');
        $this->assertSame($paginator, $result);

        $reflection = new \ReflectionClass($paginator);
        $urlMethod = $reflection->getMethod('url');

        $this->assertStringContainsString('#section', $urlMethod->invoke($paginator, 1));
    }

    #[Test]
    public function appends(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100);
        $result = $paginator->appends(['keyword' => 'test', 'page' => 999]);
        $this->assertSame($paginator, $result);

        $reflection = new \ReflectionClass($paginator);
        $urlMethod = $reflection->getMethod('url');

        $url = $urlMethod->invoke($paginator, 2);
        $this->assertStringContainsString('keyword=test', $url);
        $this->assertStringNotContainsString('page=999', $url);
        $this->assertStringContainsString('page=2', $url);
    }

    #[Test]
    public function items(): void
    {
        $data = ['a', 'b', 'c'];
        $paginator = new Bootstrap($data, 10, 1, 100);
        $this->assertSame($data, $paginator->items());
    }

    #[Test]
    public function countItems(): void
    {
        $data = ['a', 'b', 'c', 'd', 'e'];
        $paginator = new Bootstrap($data, 10, 1, 100);
        $this->assertSame(5, $paginator->count());
    }

    #[Test]
    public function isEmptyPaginator(): void
    {
        $p1 = new Bootstrap([], 10, 1, 0);
        $this->assertTrue($p1->isEmpty());

        $p2 = new Bootstrap([1], 10, 1, 1);
        $this->assertFalse($p2->isEmpty());
    }

    #[Test]
    public function each(): void
    {
        $data = [1, 2, 3];
        $paginator = new Bootstrap($data, 10, 1, 10);
        $sum = 0;

        $result = $paginator->each(function ($item) use (&$sum) {
            $sum += $item;
        });

        $this->assertSame($paginator, $result);
        $this->assertSame(6, $sum);
    }

    #[Test]
    public function eachCanBreakEarly(): void
    {
        $data = [1, 2, 3, 4, 5];
        $paginator = new Bootstrap($data, 10, 1, 10);
        $count = 0;

        $paginator->each(function () use (&$count) {
            $count++;
            if ($count >= 2) {
                return false;
            }
        });

        $this->assertSame(2, $count);
    }

    #[Test]
    public function getIterator(): void
    {
        $data = [1, 2, 3];
        $paginator = new Bootstrap($data, 10, 1, 10);
        $iterator = $paginator->getIterator();
        $this->assertInstanceOf(\Traversable::class, $iterator);

        $collected = [];
        foreach ($iterator as $item) {
            $collected[] = $item;
        }
        $this->assertSame($data, $collected);
    }

    #[Test]
    public function arrayAccess(): void
    {
        $paginator = new Bootstrap(['a', 'b', 'c'], 10, 1, 10);

        $this->assertTrue(isset($paginator[0]));
        $this->assertSame('a', $paginator[0]);
        $this->assertSame('b', $paginator[1]);

        $paginator[2] = 'z';
        $this->assertSame('z', $paginator[2]);

        unset($paginator[0]);
        $this->assertFalse(isset($paginator[0]));
    }

    #[Test]
    public function getCurrentPageDefault(): void
    {
        $this->assertSame(1, Paginator::getCurrentPage());
        $this->assertSame(42, Paginator::getCurrentPage('page', 42));
    }

    #[Test]
    public function currentPageResolver(): void
    {
        Paginator::currentPageResolver(function ($varPage) {
            return $varPage === 'page' ? 7 : 1;
        });

        $this->assertSame(7, Paginator::getCurrentPage());
        $this->assertSame(1, Paginator::getCurrentPage('other'));
    }

    #[Test]
    public function getCurrentPathDefault(): void
    {
        $this->assertSame('/', Paginator::getCurrentPath());
        $this->assertSame('/custom', Paginator::getCurrentPath('/custom'));
    }

    #[Test]
    public function currentPathResolver(): void
    {
        Paginator::currentPathResolver(function () {
            return '/api/v1/list';
        });

        $this->assertSame('/api/v1/list', Paginator::getCurrentPath());
    }

    #[Test]
    public function makeUsesBootstrapByDefault(): void
    {
        $paginator = Paginator::make([1, 2, 3], 10, 1, 100);
        $this->assertInstanceOf(Bootstrap::class, $paginator);
    }

    #[Test]
    public function makerCustomResolver(): void
    {
        $mock = $this->createMock(Bootstrap::class);
        Paginator::maker(function () use ($mock) {
            return $mock;
        });

        $result = Paginator::make([], 10, 1, 0);
        $this->assertSame($mock, $result);
    }

    #[Test]
    public function toArrayNormalMode(): void
    {
        $items = ['x', 'y', 'z'];
        $paginator = new Bootstrap($items, 10, 2, 50);
        $array = $paginator->toArray();

        $this->assertSame(50, $array['total']);
        $this->assertSame(10, $array['per_page']);
        $this->assertSame(2, $array['current_page']);
        $this->assertSame(5, $array['last_page']);
        $this->assertSame($items, $array['data']);
        $this->assertTrue($array['has_more']);
    }

    #[Test]
    public function toArraySimpleMode(): void
    {
        $items = ['a', 'b'];
        $paginator = new Bootstrap($items, 10, 1, null, true);
        $array = $paginator->toArray();

        $this->assertNull($array['total']);
        $this->assertSame(10, $array['per_page']);
        $this->assertSame(1, $array['current_page']);
        $this->assertSame($items, $array['data']);
    }

    #[Test]
    public function jsonSerialize(): void
    {
        $paginator = new Bootstrap(['a'], 10, 1, 10);
        $this->assertSame($paginator->toArray(), $paginator->jsonSerialize());
    }

    #[Test]
    public function setAndGetCollection(): void
    {
        $paginator = new Bootstrap(['a'], 10, 1, 10);
        $newItems = new Collection(['x', 'y', 'z']);
        $result = $paginator->setCollection($newItems);

        $this->assertSame($paginator, $result);
        $this->assertSame($newItems, $paginator->getCollection());
        $this->assertSame(['x', 'y', 'z'], $paginator->items());
    }

    #[Test]
    public function buildFragment(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('buildFragment');

        $this->assertSame('', $method->invoke($paginator));

        $paginator->fragment('test');
        $this->assertSame('#test', $method->invoke($paginator));
    }

    #[Test]
    public function noHasMoreOnLastPage(): void
    {
        $paginator = new Bootstrap([1, 2], 10, 10, 95);
        $this->assertFalse($this->getHasMore($paginator));
    }

    private function getHasMore(Paginator $paginator): bool
    {
        $reflection = new \ReflectionClass($paginator);
        $prop = $reflection->getProperty('hasMore');
        return $prop->getValue($paginator);
    }

    #[Test]
    public function toStringCallsRender(): void
    {
        $items = array_fill(0, 5, 'item');
        $paginator = new Bootstrap($items, 2, 1, 20);
        $string = (string) $paginator;
        $this->assertStringContainsString('pagination', $string);
        $this->assertStringContainsString('<li>', $string);
    }
}