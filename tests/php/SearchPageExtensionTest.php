<?php

namespace Fromholdio\Sherlock\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Dev\SapphireTest;

/**
 * Search links built by a page with {@see \Fromholdio\Sherlock\Extensions\SearchPageExtension}.
 *
 * A link with no search phrase keeps its empty query value, and building any link
 * raises no deprecation notice.
 */
class SearchPageExtensionTest extends SapphireTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TestSearchPage::class,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Director::config()->set('alternate_base_url', 'https://www.example.com/');
        Controller::config()->set('add_trailing_slash', false);
    }

    public static function provideLinks(): array
    {
        return [
            'no phrase' => [null, null, '?q='],
            'empty phrase' => ['', null, '?q='],
            'plain phrase' => ['widget', null, '?q=widget'],
            'phrase needing encoding' => ['red & blue widgets', null, '?q=red+%26+blue+widgets'],
            'phrase with source page' => ['widget', 42, '?q=widget&p=42'],
            'no phrase with source page' => [null, 42, '?q=&p=42'],
            'zero source page is left out' => ['widget', 0, '?q=widget'],
        ];
    }

    #[DataProvider('provideLinks')]
    public function testSearchLink(?string $phrase, ?int $sourcePageID, string $expectedQuery): void
    {
        $page = $this->createSearchPage();
        $link = $this->callWithoutDeprecations(fn () => $page->SearchLink($phrase, $sourcePageID));
        $this->assertSame('/search' . $expectedQuery, $link);
    }

    #[DataProvider('provideLinks')]
    public function testAbsoluteSearchLink(?string $phrase, ?int $sourcePageID, string $expectedQuery): void
    {
        $page = $this->createSearchPage();
        $link = $this->callWithoutDeprecations(fn () => $page->AbsoluteSearchLink($phrase, $sourcePageID));
        $this->assertSame('https://www.example.com/search' . $expectedQuery, $link);
    }

    protected function createSearchPage(): TestSearchPage
    {
        $page = TestSearchPage::create();
        $page->Title = 'Search';
        $page->URLSegment = 'search';
        $page->write();
        return $page;
    }

    /**
     * Run the callback, failing the test if it raises a deprecation notice. The handler
     * is called whatever the error_reporting level, so a notice cannot go unseen.
     */
    protected function callWithoutDeprecations(callable $callback): mixed
    {
        $deprecations = [];
        set_error_handler(
            function (int $errno, string $errstr, string $errfile, int $errline) use (&$deprecations): bool {
                $deprecations[] = $errstr . ' in ' . $errfile . ':' . $errline;
                return true;
            },
            E_DEPRECATED | E_USER_DEPRECATED
        );
        try {
            $result = $callback();
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $deprecations);
        return $result;
    }
}
