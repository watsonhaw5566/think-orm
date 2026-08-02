<?php

declare(strict_types=1);

namespace tests\unit;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use tests\TestCaseBase;
use think\paginator\driver\Bootstrap;

#[Group('unit')]
class BootstrapPaginatorTest extends TestCaseBase
{
    #[Test]
    public function renderReturnsNullWhenNoPages(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 5);
        $result = $paginator->render();
        $this->assertNull($result);
    }

    #[Test]
    public function renderNormalMode(): void
    {
        $items = array_fill(0, 2, 'item');
        $paginator = new Bootstrap($items, 2, 1, 20);
        $html = $paginator->render();

        $this->assertIsString($html);
        $this->assertStringContainsString('class="pagination"', $html);
        $this->assertStringContainsString('<ul', $html);
        $this->assertStringContainsString('</ul>', $html);
    }

    #[Test]
    public function renderSimpleMode(): void
    {
        $items = array_fill(0, 15, 'item');
        $paginator = new Bootstrap($items, 10, 1, null, true);
        $html = $paginator->render();

        $this->assertIsString($html);
        $this->assertStringContainsString('class="pager"', $html);
        $this->assertStringNotContainsString('pagination', $html);
    }

    #[Test]
    public function getLinksEmptyInSimpleMode(): void
    {
        $paginator = new Bootstrap([1], 10, 1, null, true);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getLinks');
        $method->setAccessible(true);

        $this->assertSame('', $method->invoke($paginator));
    }

    #[Test]
    public function getLinksSmallNumberOfPages(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 30);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getLinks');

        $html = $method->invoke($paginator);
        $this->assertStringNotContainsString('...', $html);
    }

    #[Test]
    public function getLinksWithDotsManyPages(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 200);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getLinks');

        $html = $method->invoke($paginator);
        $this->assertStringContainsString('...', $html);
    }

    #[Test]
    public function previousButtonDisabledOnFirstPage(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getPreviousButton');

        $html = $method->invoke($paginator);
        $this->assertStringContainsString('disabled', $html);
    }

    #[Test]
    public function previousButtonEnabled(): void
    {
        $paginator = new Bootstrap([1], 10, 5, 100);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getPreviousButton');

        $html = $method->invoke($paginator);
        $this->assertStringContainsString('<a href=', $html);
        $this->assertStringNotContainsString('disabled', $html);
    }

    #[Test]
    public function nextButtonDisabledWhenNoMore(): void
    {
        $paginator = new Bootstrap([1], 10, 10, 95);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getNextButton');

        $html = $method->invoke($paginator);
        $this->assertStringContainsString('disabled', $html);
    }

    #[Test]
    public function nextButtonEnabled(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getNextButton');

        $html = $method->invoke($paginator);
        $this->assertStringContainsString('<a href=', $html);
        $this->assertStringNotContainsString('disabled', $html);
    }

    #[Test]
    public function getDots(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getDots');

        $html = $method->invoke($paginator);
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('...', $html);
    }

    #[Test]
    public function getAvailablePageWrapper(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getAvailablePageWrapper');

        $html = $method->invoke($paginator, '/?page=5', '5');
        $this->assertStringContainsString('href="/?page=5"', $html);
        $this->assertStringContainsString('>5<', $html);
        $this->assertStringContainsString('<li>', $html);
    }

    #[Test]
    public function getDisabledTextWrapper(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getDisabledTextWrapper');

        $html = $method->invoke($paginator, '&laquo;');
        $this->assertStringContainsString('disabled', $html);
        $this->assertStringContainsString('&laquo;', $html);
    }

    #[Test]
    public function getActivePageWrapper(): void
    {
        $paginator = new Bootstrap([1], 10, 1, 100);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getActivePageWrapper');

        $html = $method->invoke($paginator, '3');
        $this->assertStringContainsString('active', $html);
        $this->assertStringContainsString('>3<', $html);
    }

    #[Test]
    public function getPageLinkWrapperActivePage(): void
    {
        $paginator = new Bootstrap([1], 10, 3, 100);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getPageLinkWrapper');

        $html = $method->invoke($paginator, '/?page=3', '3');
        $this->assertStringContainsString('active', $html);
        $this->assertStringNotContainsString('<a href=', $html);
    }

    #[Test]
    public function getPageLinkWrapperInactivePage(): void
    {
        $paginator = new Bootstrap([1], 10, 3, 100);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getPageLinkWrapper');

        $html = $method->invoke($paginator, '/?page=5', '5');
        $this->assertStringContainsString('<a href=', $html);
        $this->assertStringNotContainsString('active', $html);
    }

    #[Test]
    public function getUrlLinksGenerateMultipleLinks(): void
    {
        $paginator = new Bootstrap([1], 10, 3, 100);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getUrlLinks');

        $urls = [
            1 => '/?page=1',
            2 => '/?page=2',
            3 => '/?page=3',
        ];
        $html = $method->invoke($paginator, $urls);
        $this->assertStringContainsString('/?page=1', $html);
        $this->assertStringContainsString('/?page=2', $html);
        $this->assertStringContainsString('>3<', $html);
    }

    #[Test]
    public function buttonsContainCorrectHtmlEntities(): void
    {
        $items = array_fill(0, 15, 'item');
        $paginator = new Bootstrap($items, 10, 2, 100);
        $html = $paginator->render();

        $this->assertStringContainsString('&laquo;', $html);
        $this->assertStringContainsString('&raquo;', $html);
    }

    #[Test]
    public function currentPageIsMarkedActive(): void
    {
        $paginator = new Bootstrap([1], 10, 5, 100);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getLinks');

        $html = $method->invoke($paginator);
        $this->assertStringContainsString('class="active"', $html);
    }

    #[Test]
    public function middlePageShowsSlider(): void
    {
        $paginator = new Bootstrap([1], 10, 10, 200);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getLinks');

        $html = $method->invoke($paginator);
        $this->assertStringContainsString('...', $html);
        $count = substr_count($html, '...');
        $this->assertSame(2, $count);
    }

    #[Test]
    public function firstPageRangeShowsFirstPages(): void
    {
        $paginator = new Bootstrap([1], 10, 2, 200);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getLinks');

        $html = $method->invoke($paginator);
        $this->assertStringContainsString('href="/?page=1"', $html);
        $this->assertStringContainsString('>2<', $html);
    }

    #[Test]
    public function lastPageRangeShowsLastPages(): void
    {
        $paginator = new Bootstrap([1], 10, 19, 200);
        $reflection = new \ReflectionClass($paginator);
        $method = $reflection->getMethod('getLinks');

        $html = $method->invoke($paginator);
        $this->assertStringContainsString('href="/?page=20"', $html);
    }
}