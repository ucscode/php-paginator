<?php

namespace Ucscode\Paginator\Tests;

use DOMDocument;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Ucscode\Paginator\Paginator;

class PaginatorTest extends TestCase
{
    protected Paginator $paginator;

    protected function setUp(): void
    {
        $numItems = 100;
        $itemsPerPage = 10;
        $currentPage = 5;
        $urlPattern = '/example/page(:num)';

        $this->paginator = new Paginator($numItems, $itemsPerPage, $currentPage, $urlPattern);
    }

    public function testGetNextPage()
    {
        $this->paginator->setCurrentPage(1);
        $this->assertEquals(2, $this->paginator->getNextPage());

        // If we're on the last page, getNextPage() returns null.
        $this->paginator->setCurrentPage($this->paginator->getNumPages());
        $this->assertNull($this->paginator->getNextPage());
    }

    public function testGetPrevPage()
    {
        $this->paginator->setCurrentPage(2);
        $this->assertEquals(1, $this->paginator->getPrevPage());

        // If we're on the first page, getPrevPage() returns null.
        $this->paginator->setCurrentPage(1);
        $this->assertNull($this->paginator->getPrevPage());
    }

    public function testGetNextUrl()
    {
        $this->paginator->setCurrentPage(1);
        $this->paginator->setUrlPattern('/example/page(:num)');
        $this->assertEquals('/example/page2', $this->paginator->getNextUrl());

        // Returns null if on the last page.
        $this->paginator->setCurrentPage($this->paginator->getNumPages());
        $this->assertNull($this->paginator->getNextUrl());
    }

    public function testGetPrevUrl()
    {
        $this->paginator->setCurrentPage(2);
        $this->paginator->setUrlPattern('/example/page(:num)');
        $this->assertEquals('/example/page1', $this->paginator->getPrevUrl());

        // Returns null if on the first page.
        $this->paginator->setCurrentPage(1);
        $this->assertNull($this->paginator->getPrevUrl());
    }

    public function testToHtml()
    {
        $this->assertIsString($this->paginator->toHtml());
    }

    public function testSetInnerHtml()
    {
        $paginator = new class extends Paginator 
        {
            public function getInnerHTML(string $html): string
            {
                $document = new DOMDocument();
                $element = $document->createElement('div');
                $this->setInnerHTML($element, $html);
                return $document->saveHTML($element->firstChild);
            }
        };

        $htmlTemplate = "<p>Sample: %s</p>";

        $this->assertSame(
            sprintf($htmlTemplate, '&amp; &#xAB;'),
            $paginator->getInnerHTML(sprintf($htmlTemplate, '& &laquo;'))
        );
    }

    #[DataProvider('getTestData')]
    public function testGetPages($numPages, $currentPage, $maxPages, $expected)
    {
        $paginator = new Paginator($numPages, 1, $currentPage);
        $paginator->setMaxPagesToShow($maxPages);

        $pages = $paginator->getPages();
        $pageNums = array_map(function($page) { return $page['num']; }, $pages);

        $this->assertEquals($expected, $pageNums);
    }

    public static function getTestData()
    {
        return array(
            // num pages, current page, max pages to show, expected pagination
            array(13, 2, 5, array(1, 2, 3, 4, '...', 13)),
            array(13, 4, 5, array(1, '...', 3, 4, 5, '...', 13)),
            array(13, 5, 5, array(1, '...', 4, 5, 6, '...', 13)),
            array(13, 11, 5, array(1, '...', 10, 11, 12, 13)),
            array(13, 10, 5, array(1, '...', 9, 10, 11, '...', 13)),
            array(20, 1, 10, array(1, 2, 3, 4, 5, 6, 7, 8, 9, '...', 20)),
            array(20, 2, 10, array(1, 2, 3, 4, 5, 6, 7, 8, 9, '...', 20)),
            array(20, 20, 10, array(1, '...', 12, 13, 14, 15, 16, 17, 18, 19, 20)),
            array(20, 19, 10, array(1, '...', 12, 13, 14, 15, 16, 17, 18, 19, 20)),
            array(20, 10, 10, array(1, '...', 7, 8, 9, 10, 11, 12, 13, 14, '...', 20)),
            array(20, 9, 10, array(1, '...', 6, 7, 8, 9, 10, 11, 12, 13, '...', 20)),
            array(5, 3, 10, array(1, 2, 3, 4, 5)),
            array(1, 1, 10, array()), // No pagination if there's only one page.
            array(20, 5, 3, array(1, '...', 5, '...', 20)),
        );
    }

    #[DataProvider('getRangeData')]
    public function testGetItemRanges($numItems, $itemsPerPage, $currentPage, $expectedFirst, $expectedLast)
    {
        $paginator = new Paginator($numItems, $itemsPerPage, $currentPage);

        $this->assertEquals($numItems, $paginator->getTotalItems());
        $this->assertEquals($expectedFirst, $paginator->getCurrentPageFirstItem());
        $this->assertEquals($expectedLast, $paginator->getCurrentPageLastItem());

    }

    public static function getRangeData()
    {
        return array(
            // $numItems, $itemsPerPage, $currentPage, $expectedFirstItem, $expectedLastItem
            array(95, 10, 1, 1, 10),
            array(95, 10, 2, 11, 20),
            array(95, 10, 10, 91, 95),
            array(95, 10, 11, null, null), // If current page exceeds total items, first and last item are null.
        );
    }

}