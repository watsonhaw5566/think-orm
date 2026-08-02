<?php

declare(strict_types=1);

namespace tests\unit;

use DateInterval;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use tests\TestCaseBase;
use think\db\CacheItem;
use think\db\exception\InvalidArgumentException;
use stdClass;

#[Group('unit')]
class CacheItemTest extends TestCaseBase
{
    #[Test]
    public function constructWithKey(): void
    {
        $item = new CacheItem('cache_key');
        $this->assertSame('cache_key', $item->getKey());
    }

    #[Test]
    public function constructWithoutKey(): void
    {
        $item = new CacheItem();
        $this->assertNull($item->getKey());
    }

    #[Test]
    public function setKeyReturnsSelf(): void
    {
        $item   = new CacheItem();
        $result = $item->setKey('new_key');
        $this->assertSame($item, $result);
        $this->assertSame('new_key', $item->getKey());
    }

    #[Test]
    public function setKeyOverwritesExisting(): void
    {
        $item = new CacheItem('original_key');
        $item->setKey('updated_key');
        $this->assertSame('updated_key', $item->getKey());
    }

    #[Test]
    public function getDefaultReturnsNull(): void
    {
        $item = new CacheItem('key');
        $this->assertNull($item->get());
    }

    #[Test]
    public function isHitDefaultFalse(): void
    {
        $item = new CacheItem('key');
        $this->assertFalse($item->isHit());
    }

    #[Test]
    public function setValueAndIsHit(): void
    {
        $item   = new CacheItem('key');
        $result = $item->set('cached_value');
        $this->assertSame($item, $result);
        $this->assertSame('cached_value', $item->get());
        $this->assertTrue($item->isHit());
    }

    #[Test]
    public function setValueWithDifferentTypes(): void
    {
        $item = new CacheItem('key');

        $item->set(42);
        $this->assertSame(42, $item->get());
        $this->assertTrue($item->isHit());

        $item->set(['a' => 1, 'b' => 2]);
        $this->assertSame(['a' => 1, 'b' => 2], $item->get());

        $item->set(new stdClass());
        $this->assertInstanceOf(stdClass::class, $item->get());

        $item->set(null);
        $this->assertNull($item->get());
        $this->assertTrue($item->isHit());
    }

    #[Test]
    public function tagReturnsSelf(): void
    {
        $item   = new CacheItem('key');
        $result = $item->tag('my_tag');
        $this->assertSame($item, $result);
        $this->assertSame('my_tag', $item->getTag());
    }

    #[Test]
    public function tagWithArray(): void
    {
        $item = new CacheItem('key');
        $tags = ['tag1', 'tag2'];
        $item->tag($tags);
        $this->assertSame($tags, $item->getTag());
    }

    #[Test]
    public function tagWithNull(): void
    {
        $item = new CacheItem('key');
        $item->tag(null);
        $this->assertNull($item->getTag());
    }

    #[Test]
    public function expireWithNull(): void
    {
        $item   = new CacheItem('key');
        $result = $item->expire(null);
        $this->assertSame($item, $result);
        $this->assertNull($item->getExpire());
    }

    #[Test]
    public function expireWithNumeric(): void
    {
        $item = new CacheItem('key');
        $item->expire(3600);
        $expire = $item->getExpire();
        $this->assertNotNull($expire);
        $this->assertIsInt($expire);
        $this->assertGreaterThan(0, $expire);
        $this->assertLessThanOrEqual(3600, $expire);
    }

    #[Test]
    public function expireWithDateInterval(): void
    {
        $item     = new CacheItem('key');
        $interval = new DateInterval('PT1H');
        $result   = $item->expire($interval);
        $this->assertSame($item, $result);
        $expire = $item->getExpire();
        $this->assertNotNull($expire);
        $this->assertIsInt($expire);
    }

    #[Test]
    public function expireWithDateTimeInterface(): void
    {
        $item   = new CacheItem('key');
        $future = new DateTime('+1 hour');
        $result = $item->expire($future);
        $this->assertSame($item, $result);
        $this->assertSame($future, $item->getExpire());
    }

    #[Test]
    public function expireWithInvalidTypeThrowsException(): void
    {
        $item = new CacheItem('key');
        $this->expectException(InvalidArgumentException::class);
        $item->expire('invalid');
    }

    #[Test]
    public function expiresAtWithDateTimeInterface(): void
    {
        $item   = new CacheItem('key');
        $date   = new DateTimeImmutable('+2 hours');
        $result = $item->expiresAt($date);
        $this->assertSame($item, $result);
        $this->assertSame($date, $item->getExpire());
    }

    #[Test]
    public function expiresAfterWithNumeric(): void
    {
        $item   = new CacheItem('key');
        $result = $item->expiresAfter(1800);
        $this->assertSame($item, $result);
        $expire = $item->getExpire();
        $this->assertNotNull($expire);
        $this->assertIsInt($expire);
        $this->assertLessThanOrEqual(1800, $expire);
    }

    #[Test]
    public function expiresAfterWithDateInterval(): void
    {
        $item     = new CacheItem('key');
        $interval = new DateInterval('PT30M');
        $result   = $item->expiresAfter($interval);
        $this->assertSame($item, $result);
        $expire = $item->getExpire();
        $this->assertNotNull($expire);
        $this->assertIsInt($expire);
    }

    #[Test]
    public function expiresAfterWithInvalidTypeThrowsException(): void
    {
        $item = new CacheItem('key');
        $this->expectException(InvalidArgumentException::class);
        $item->expiresAfter('invalid_type');
    }

    #[Test]
    public function setAndGetComplexValue(): void
    {
        $item        = new CacheItem('complex');
        $complexData = [
            'user'     => ['id' => 1, 'name' => 'test'],
            'roles'    => ['admin', 'user'],
            'settings' => new stdClass(),
        ];
        $item->set($complexData);
        $this->assertSame($complexData, $item->get());
        $this->assertTrue($item->isHit());
    }

    #[Test]
    public function defaultTagIsNull(): void
    {
        $item = new CacheItem('key');
        $this->assertNull($item->getTag());
    }
}
