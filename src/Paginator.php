<?php

namespace Ucscode\Paginator;

use DOMDocument;
use DOMElement;
use DOMText;
use Ucscode\DOMElement\DOMElementNameInterface;

class Paginator
{
    public const NUM_PLACEHOLDER = '(:num)';

    protected int $totalItems;
    protected int $numPages;
    protected int $itemsPerPage;
    protected int $currentPage;
    protected string $urlPattern;
    protected int $maxPagesToShow = 10;
    protected string $previousText = '&laquo; Previous';
    protected string $nextText = 'Next &raquo;';

    /**
     * @param int $totalItems The total number of items.
     * @param int $itemsPerPage The number of items per page.
     * @param int $currentPage The current page number.
     * @param string $urlPattern A URL for each page, with (:num) as a placeholder for the page number. Ex. '/foo/page/(:num)'
     */
    public function __construct(int $totalItems = 0, int $itemsPerPage = 10, int $currentPage = 1, string $urlPattern = '')
    {
        $this->totalItems = $totalItems;
        $this->itemsPerPage = $itemsPerPage;
        $this->currentPage = $currentPage;
        $this->urlPattern = $urlPattern;

        $this->updateNumPages();
    }

    public function __toString()
    {
        return $this->toHtml();
    }

    /**
     * @param int $maxPagesToShow
     * @throws \InvalidArgumentException if $maxPagesToShow is less than 3.
     */
    public function setMaxPagesToShow(int $maxPagesToShow): static
    {
        if ($maxPagesToShow < 3) {
            throw new \InvalidArgumentException('maxPagesToShow cannot be less than 3.');
        }
        $this->maxPagesToShow = $maxPagesToShow;
        return $this;
    }

    /**
     * @return int
     */
    public function getMaxPagesToShow(): int
    {
        return $this->maxPagesToShow;
    }

    /**
     * @param int $currentPage
     */
    public function setCurrentPage(int $currentPage): static
    {
        $this->currentPage = $currentPage;
        return $this;
    }

    /**
     * @return int
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }

    /**
     * @param int $itemsPerPage
     */
    public function setItemsPerPage(int $itemsPerPage): static
    {
        $this->itemsPerPage = $itemsPerPage;
        $this->updateNumPages();
        return $this;
    }

    /**
     * @return int
     */
    public function getItemsPerPage(): int
    {
        return $this->itemsPerPage;
    }

    /**
     * @param int $totalItems
     */
    public function setTotalItems(int $totalItems): static
    {
        $this->totalItems = $totalItems;
        $this->updateNumPages();
        return $this;
    }

    /**
     * @return int
     */
    public function getTotalItems(): int
    {
        return $this->totalItems;
    }

    /**
     * @return int
     */
    public function getNumPages(): int
    {
        return $this->numPages;
    }

    /**
     * @param string $urlPattern
     */
    public function setUrlPattern(string $urlPattern): static
    {
        $this->urlPattern = $urlPattern;
        return $this;
    }

    /**
     * @return string
     */
    public function getUrlPattern(): string
    {
        return $this->urlPattern;
    }

    /**
     * @param int $pageNum
     * @return string
     */
    public function getPageUrl(int $pageNum): string
    {
        return str_replace(self::NUM_PLACEHOLDER, $pageNum, $this->urlPattern);
    }

    public function getNextPage(): ?int
    {
        if ($this->currentPage < $this->numPages) {
            return $this->currentPage + 1;
        }

        return null;
    }

    public function getPrevPage(): ?int
    {
        if ($this->currentPage > 1) {
            return $this->currentPage - 1;
        }

        return null;
    }

    public function getNextUrl(): ?string
    {
        if (!$this->getNextPage()) {
            return null;
        }

        return $this->getPageUrl($this->getNextPage());
    }

    /**
     * @return string|null
     */
    public function getPrevUrl(): ?string
    {
        if (!$this->getPrevPage()) {
            return null;
        }

        return $this->getPageUrl($this->getPrevPage());
    }

    /**
     * Get an array of paginated page data.
     *
     * Example:
     * array(
     *     array ('num' => 1,     'url' => '/example/page/1',  'isCurrent' => false),
     *     array ('num' => '...', 'url' => NULL,               'isCurrent' => false),
     *     array ('num' => 3,     'url' => '/example/page/3',  'isCurrent' => false),
     *     array ('num' => 4,     'url' => '/example/page/4',  'isCurrent' => true ),
     *     array ('num' => 5,     'url' => '/example/page/5',  'isCurrent' => false),
     *     array ('num' => '...', 'url' => NULL,               'isCurrent' => false),
     *     array ('num' => 10,    'url' => '/example/page/10', 'isCurrent' => false),
     * )
     *
     * @return array
     */
    public function getPages(): array
    {
        $pages = array();

        if ($this->numPages <= 1) {
            return array();
        }

        if ($this->numPages <= $this->maxPagesToShow) {
            for ($i = 1; $i <= $this->numPages; $i++) {
                $pages[] = $this->createPage($i, $i == $this->currentPage);
            }
        } else {

            // Determine the sliding range, centered around the current page.
            $numAdjacents = (int) floor(($this->maxPagesToShow - 3) / 2);

            if ($this->currentPage + $numAdjacents > $this->numPages) {
                $slidingStart = $this->numPages - $this->maxPagesToShow + 2;
            } else {
                $slidingStart = $this->currentPage - $numAdjacents;
            }
            if ($slidingStart < 2) {
                $slidingStart = 2;
            }

            $slidingEnd = $slidingStart + $this->maxPagesToShow - 3;
            if ($slidingEnd >= $this->numPages) {
                $slidingEnd = $this->numPages - 1;
            }

            // Build the list of pages.
            $pages[] = $this->createPage(1, $this->currentPage == 1);
            if ($slidingStart > 2) {
                $pages[] = $this->createPageEllipsis();
            }
            for ($i = $slidingStart; $i <= $slidingEnd; $i++) {
                $pages[] = $this->createPage($i, $i == $this->currentPage);
            }
            if ($slidingEnd < $this->numPages - 1) {
                $pages[] = $this->createPageEllipsis();
            }
            $pages[] = $this->createPage($this->numPages, $this->currentPage == $this->numPages);
        }


        return $pages;
    }

    /**
     * Render an HTML pagination control.
     *
     * @return string
     */
    public function toHtml(): string
    {
        $document = new DOMDocument('1.0', 'UTF-8');
        $element = $this->getElement($document);
        return $document->saveHTML($element);
    }

    public function getCurrentPageFirstItem(): ?int
    {
        $first = ($this->currentPage - 1) * $this->itemsPerPage + 1;

        if ($first > $this->totalItems) {
            return null;
        }

        return $first;
    }

    public function getCurrentPageLastItem(): ?int
    {
        $first = $this->getCurrentPageFirstItem();
        if ($first === null) {
            return null;
        }

        $last = $first + $this->itemsPerPage - 1;
        if ($last > $this->totalItems) {
            return $this->totalItems;
        }

        return $last;
    }

    public function setPreviousText(string $text): static
    {
        $this->previousText = $text;
        return $this;
    }

    public function getPreviousText(): string
    {
        return $this->previousText;
    }

    public function setNextText(string $text): static
    {
        $this->nextText = $text;
        return $this;
    }

    public function getNextText(): string
    {
        return $this->nextText;
    }

    protected function updateNumPages(): void
    {
        $this->numPages = ($this->itemsPerPage == 0 ? 0 : (int) ceil($this->totalItems / $this->itemsPerPage));
    }

    /**
     * Create a page data structure.
     *
     * @param int $pageNum
     * @param bool $isCurrent
     * @return Array
     */
    protected function createPage(int $pageNum, bool $isCurrent = false): array
    {
        return array(
            'num' => $pageNum,
            'url' => $this->getPageUrl($pageNum),
            'isCurrent' => $isCurrent,
        );
    }

    /**
     * @return array
     */
    protected function createPageEllipsis(): array
    {
        return array(
            'num' => '...',
            'url' => null,
            'isCurrent' => false,
        );
    }

    protected function getElement(DOMDocument $document): DOMElement
    {
        $ul = $document->createElement(DOMElementNameInterface::NODE_UL);
        $ul->setAttribute('class', 'pagination');

        if($this->getPrevUrl()) {
            $prevLiElement = $this->createListElement($document, null, $this->getPrevUrl(), $this->getPreviousText());
            $ul->appendChild($prevLiElement);
        }

        foreach($this->getPages() as $page) {
            if(!empty($page['url'])) {
                $pagerElement = $this->createListElement($document, $page['isCurrent'] ? 'active' : null, $page['url'], $page['num']);
            } else {
                $pagerElement = $this->createListElement($document, 'disabled', null, $page['num']);
            };
            $ul->appendChild($pagerElement);
        };

        if($this->getNextUrl()) {
            $nextLiElement = $this->createListElement($document, null, $this->getNextUrl(), $this->getNextText());
            $ul->appendChild($nextLiElement);
        }

        $nav = $document->createElement(DOMElementNameInterface::NODE_DIV);
        $nav->setAttribute('class', 'navigation');
        $nav->appendChild($ul);

        return $nav;
    }

    /**
     * @method createListElement
     */
    protected function createListElement(DOMDocument $document, ?string $class, ?string $href, string $label): DOMElement
    {
        $node = $document->createElement(!is_null($href) ? DOMElementNameInterface::NODE_A : DOMElementNameInterface::NODE_SPAN);
        $node->setAttribute('class', 'page-link');
        $this->setInnerHTML($node, $label);
        empty($href) ? null : $node->setAttribute('href', $href);

        $li = $document->createElement(DOMElementNameInterface::NODE_LI);
        $li->setAttribute('class', 'page-item ' . $class);
        $li->appendChild($node);

        return $li;
    }

    /**
     * Set the innerHTML of a DOMElement
     */
    protected function setInnerHTML(DOMElement $element, string $html)
    {
        // Create a new temporary DOMDocument to load the HTML content
        $tempDoc = new \DOMDocument();
        
        // Suppress errors related to invalid HTML structure
        libxml_use_internal_errors(true);
        
        // Load the HTML content
        $tempDoc->loadHTML(sprintf('<div>%s</div>', $html), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        // Clear libxml errors
        libxml_clear_errors();
        
        // Import the loaded HTML into the original document
        // The importedFragment is literally a DIVElement
        $importedFragment = $element->ownerDocument->importNode($tempDoc->documentElement, true);

        // Remove all existing children of the element
        while ($element->firstChild) {
            $element->removeChild($element->firstChild);
        }
        
        // Append the imported fragment
        foreach ($importedFragment->childNodes as $child) {
            $element->appendChild($child->cloneNode(true));
        }
    } 
}
