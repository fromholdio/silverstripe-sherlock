<?php

namespace Fromholdio\Sherlock\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;

class SearchLog extends DataObject
{
    private static $table_name = 'SearchLog';
    private static $singular_name = 'Search Log';
    private static $plural_name = 'Search Logs';

    private static $db = [
        'Phrase' => 'Varchar',
        'HasDirectResult' => 'Boolean',
        'ResultsCount' => 'Int',
        'Duration' => 'Float',
        'Stage' => 'Varchar'
    ];

    private static $has_one = [
        'SearchEngine' => SearchEngine::class,
        'SearchPage' => SiteTree::class
    ];

    private static $summary_fields = [
        'Created.Nice' => 'Time',
        'Phrase',
        'DurationSummary' => 'Duration',
        'ResultsCount' => 'Results',
        'SearchPage.Title' => 'Page'
    ];

    private static $default_sort = 'Created DESC';

    public function getDurationSummary()
    {
        return round($this->Duration, 5);
    }
}
