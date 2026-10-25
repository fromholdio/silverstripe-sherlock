<?php

namespace Fromholdio\Sherlock\Tests;

use Fromholdio\Sherlock\Model\SearchEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Dev\SapphireTest;

/**
 * Absolute results links built by a {@see SearchEngine}, which hands the phrase and
 * source page ID on to its target search page.
 */
class SearchEngineTest extends SapphireTest
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

    public static function provideAbsoluteLinks(): array
    {
        return [
            'no phrase' => [null, null, '?q='],
            'phrase needing encoding' => ['red & blue widgets', null, '?q=red+%26+blue+widgets'],
            'phrase with source page' => ['widget', 42, '?q=widget&p=42'],
            'no phrase with source page' => [null, 42, '?q=&p=42'],
        ];
    }

    #[DataProvider('provideAbsoluteLinks')]
    public function testAbsoluteLink(?string $phrase, ?int $sourcePageID, string $expectedQuery): void
    {
        $engine = $this->createEngine();
        $this->assertSame(
            'https://www.example.com/search' . $expectedQuery,
            $engine->AbsoluteLink($phrase, $sourcePageID)
        );
    }

    public function testAbsoluteLinkWithoutArguments(): void
    {
        $engine = $this->createEngine();
        $this->assertSame('https://www.example.com/search?q=', $engine->AbsoluteLink());
    }

    protected function createEngine(): SearchEngine
    {
        $page = TestSearchPage::create();
        $page->Title = 'Search';
        $page->URLSegment = 'search';
        $page->write();

        $engine = SearchEngine::create();
        $engine->Title = 'Test engine';
        $engine->DefaultSearchPageID = $page->ID;
        $engine->write();
        return $engine;
    }
}
