<?php

namespace Fromholdio\Sherlock\Model;

use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

class SearchLog extends DataObject implements PermissionProvider
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

    public function canCreate($member = null, $context = [])
    {
        return false;
    }

    public function canView($member = null)
    {
        return Permission::checkMember($member, 'VIEW_SEARCH_LOGS');
    }

    public function canEdit($member = null)
    {
        return false;
    }

    public function canDelete($member = null)
    {
        return false;
    }

    public function providePermissions() {
        return [
            'VIEW_SEARCH_LOGS' => [
                'name' => 'View search logs',
                'category' => 'Search engines',
            ]
        ];
    }
}
