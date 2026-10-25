<?php

namespace Fromholdio\Sherlock\Tests\SearchFormTest;

use Fromholdio\Sherlock\Model\SearchEngine;
use SilverStripe\Dev\TestOnly;

/**
 * Test-only search engine that records each results link it is asked to build, so
 * tests can tell whether a submission reached the results redirect.
 */
class TestSearchEngine extends SearchEngine implements TestOnly
{
    private static string $table_name = 'SherlockTest_SearchEngine';

    public static array $linkCalls = [];

    public function Link($searchPhrase = null, ?int $sourcePageID = null)
    {
        static::$linkCalls[] = [$searchPhrase, $sourcePageID];
        return parent::Link($searchPhrase, $sourcePageID);
    }
}
