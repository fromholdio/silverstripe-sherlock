<?php

namespace Fromholdio\Sherlock\Tests;

use Fromholdio\Sherlock\Extensions\SearchPageExtension;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\TestOnly;

/**
 * Test-only search results page, built the way a project adds Sherlock to a page type.
 */
class TestSearchPage extends SiteTree implements TestOnly
{
    private static string $table_name = 'SherlockTest_SearchPage';

    private static array $extensions = [
        SearchPageExtension::class,
    ];
}
