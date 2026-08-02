<?php

declare(strict_types=1);

namespace tests\unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use tests\TestCaseBase;

#[Group('unit')]
class ExampleUnitTest extends TestCaseBase
{
    #[Test]
    public function exampleAssertion(): void
    {
        $this->assertTrue(true);
    }

    public function testArrayOperations(): void
    {
        $array = [1, 2, 3];
        $this->assertCount(3, $array);
        $this->assertEquals(1, $array[0]);
    }

    public function testStringOperations(): void
    {
        $string = 'think-orm';
        $this->assertEquals(9, strlen($string));
        $this->assertStringContainsString('think', $string);
    }
}