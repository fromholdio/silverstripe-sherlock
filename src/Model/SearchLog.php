<?php

namespace Fromholdio\Sherlock\Model;

use Override;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\ORM\DataObject;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;

class SearchLog extends DataObject implements PermissionProvider
{
    private static string $table_name = 'SearchLog';

    private static string $singular_name = 'Search Log';

    private static string $plural_name = 'Search Logs';

    private static array $db = [
        'Phrase' => 'Varchar',
        'HasDirectResult' => 'Boolean',
        'ResultsCount' => 'Int',
        'Duration' => 'Float',
        'Stage' => 'Varchar'
    ];

    private static array $has_one = [
        'SearchEngine' => SearchEngine::class,
        'SearchPage' => SiteTree::class
    ];

    private static array $summary_fields = [
        'Created.Nice' => 'Time',
        'Phrase',
        'DurationSummary' => 'Duration',
        'ResultsCount' => 'Results',
        'SearchPage.Title' => 'Page'
    ];

    private static string $default_sort = 'Created DESC';

    public function getDurationSummary(): float
    {
        return round($this->Duration, 5);
    }

    #[Override]
    public function canCreate($member = null, $context = []): bool
    {
        return false;
    }

    #[Override]
    public function canView($member = null)
    {
        return Permission::checkMember($member, 'VIEW_SEARCH_LOGS');
    }

    #[Override]
    public function canEdit($member = null): bool
    {
        return false;
    }

    #[Override]
    public function canDelete($member = null): bool
    {
        return false;
    }

    public function providePermissions(): array
    {
        return [
            'VIEW_SEARCH_LOGS' => [
                'name' => 'View search logs',
                'category' => 'Search engines',
            ]
        ];
    }
}
