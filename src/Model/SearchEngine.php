<?php

namespace Fromholdio\Sherlock\Model;

use Fromholdio\Sherlock\SearchAction;
use Fromholdio\CommonAncestor\CommonAncestor;
use Fromholdio\Sherlock\Extensions\SearchPageExtension;
use Sheadawson\DependentDropdown\Forms\DependentDropdownField;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class SearchEngine extends DataObject
{
    private static $table_name = 'SearchEngine';
    private static $singular_name = 'Search Engine';
    private static $plural_name = 'Search Engines';

    private static $engine_entry_class;
    private static $engine_config;
    private static $engine_log_enabled = true;

    private static $db = [
        'Title' => 'Varchar',
        'Description' => 'Varchar',
        'SortMode' => 'Varchar(30)',
        'SortDirection' => 'Varchar(10)',
        'DirectSortMode' => 'Varchar(30)',
        'DirectSortDirection' => 'Varchar(10)'
    ];

    private static $has_one = [
        'DefaultSearchPage' => SiteTree::class
    ];

    private static $has_many = [
        'Logs' => SearchLog::class
    ];

    private static $summary_fields = [
        'Title',
        'Description',
        'EntriesCount' => 'Entries'
    ];

    private static $cascade_deletes = [
        'getEntries',
        'Logs'
    ];

    protected static $search_page_classes = [];

    public static function register_search_page_class($class)
    {
        self::$search_page_classes[$class] = $class;
    }

    public function Link($searchPhrase = null, int $sourcePageID = null)
    {
        $searchPage = $this->getTargetSearchPage();
        if (!$searchPage) {
            return null;
        }
        return $searchPage->SearchLink($searchPhrase, $sourcePageID);
    }

    public function AbsoluteLink($searchPhrase = null, int $sourcePageID = null)
    {
        $searchPage = $this->getTargetSearchPage();
        if (!$searchPage) {
            return null;
        }
        return $searchPage->SearchAbsoluteLink($searchPhrase, $sourcePageID);
    }

    public function EntriesCount()
    {
        $entries = $this->getEntries();
        if (!$entries) {
            return 0;
        }
        return $entries->count();
    }

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $fields->removeByName([
            'DefaultSearchPageID',
            'SortMode',
            'SortDirection',
            'DirectSortMode',
            'DirectSortDirection'
        ]);

        if (!$this->isInDB()) {
            return $fields;
        }

        $availableSearchPages = $this->getAvailableSearchPages();
        if ($availableSearchPages) {
            $searchPageField = DropdownField::create(
                'DefaultSearchPageID',
                $this->fieldLabel('DefaultSearchPage'),
                $availableSearchPages->map()->toArray()
            );
            $searchPageField->setHasEmptyDefault(true);
            $searchPageField->setEmptyString('- Please select (optional) -');
            $searchPageField->setDescription(
                'Set a default search page that searches of this engine should be redirected to.'
            );
        }
        else {
            $searchPageField = ReadonlyField::create(
                'DefaultSearchPageInfo',
                $this->fieldLabel('DefaultSearchPage'),
                'A search page must be created and attached to this engine first.'
            );
        }

        $directSortFields = $this->getSortFields(
            'DirectSortMode',
            'DirectSortDirection',
            'direct'
        );
        if ($directSortFields) {
            $fields->addFieldsToTab('Root.Main', $directSortFields);
        }

        $sortFields = $this->getSortFields(
            'SortMode',
            'SortDirection',
            'fields'
        );
        if ($sortFields) {
            $fields->addFieldsToTab('Root.Main', $sortFields);
        }

        $fields->addFieldToTab('Root.Main', $searchPageField);

        return $fields;
    }

    public function onBeforeWrite()
    {
        parent::onBeforeWrite();
        if ($this->DefaultSearchPageID) {
            $defaultSearchPage = $this->DefaultSearchPage();
            if (!$defaultSearchPage->has_extension(SearchPageExtension::class)) {
                $this->DefaultSearchPageID = 0;
            }
        }
    }

    protected function addStarsToKeywords($keywords)
    {
        if (!trim($keywords)) {
            return "";
        }
        // Add * to each keyword
        $splitWords = preg_split("/ +/", trim($keywords));
        $newWords = [];
        for ($i = 0; $i < count($splitWords); $i++) {
            $word = $splitWords[$i];
            if ($word[0] == '"') {
                while (++$i < count($splitWords)) {
                    $subword = $splitWords[$i];
                    $word .= ' ' . $subword;
                    if (substr($subword, -1) == '"') {
                        break;
                    }
                }
            } else {
                $word .= '*';
            }
            $newWords[] = $word;
        }
        return implode(" ", $newWords);
    }

    public function search(string $phrase = null, int $searchPageID = null)
    {
        return SearchAction::create(
            $phrase,
            $this,
            $searchPageID,
            $this->isLogEnabled()
        );
    }

    public function getDirectSearchResult($phrase = null)
    {
        if (!$phrase) {
            return null;
        }

        if (!is_string($phrase)) {
            throw new \InvalidArgumentException(
                'Invalid $phrase passed to ' . ClassInfo::class_name($this) . '::getDirectSearchResults(). '
                . 'Supplied ' . gettype($phrase) . ' but expected variable type null or string.'
            );
        }

        $phrase = strtolower(trim($phrase));
        $entries = $this->getEntries();

        $directFilter = $this->getDirectSearchFilter($phrase);
        if ($directFilter) {
            $directResults = $entries->filterAny($directFilter);
            if ($directResults->count() > 0) {
                $directSortSQL = $this->getSortSQL('direct');
                if ($directSortSQL) {
                    $directResults->sort($directSortSQL);
                }
                $directMatchFirst = $directResults->first();
                return $directMatchFirst->getRecord();
            }
        }
        return null;
    }

    public function getSearchResults($phrase = null)
    {
        if (!$phrase) {
            return null;
        }

        if (!is_string($phrase)) {
            throw new \InvalidArgumentException(
                'Invalid $phrase passed to ' . ClassInfo::class_name($this) . '::getSearchResults(). '
                . 'Supplied ' . gettype($phrase) . ' but expected variable type null or string.'
            );
        }

        $phrase = strtolower(trim($phrase));
        $entries = $this->getEntries();

        $searchFilter = $this->getSearchFilter($phrase);
        if ($searchFilter) {
            $searchResults = $entries->filterAny($searchFilter);
            if ($searchResults->count() > 0) {
                $sortSQL = $this->getSortSQL('fields');
                if ($sortSQL) {
                    $searchResults = $searchResults->sort($sortSQL);
                }
                return $this->getRecords($searchResults);
            }
        }

        return null;
    }

    public function getEntry($record)
    {
        $class = $this->getEntryClass();

        $entryIsVersioned = $class::singleton()->hasExtension(Versioned::class);
        $recordIsVersioned = $record->hasExtension(Versioned::class);

        if (
            ($entryIsVersioned && !$recordIsVersioned)
            || (!$entryIsVersioned && $recordIsVersioned)
        ) {
            throw new \LogicException(
                'Your SearchEngine ' . static::class . ' has an entry class of '
                . $class . ' and a record class of ' . get_class($record) . '. The entry class '
                . ' and record class must either both be extended by Versioned or both not.'
            );
        }

        $entries = $class::get();
        $filter = $this->getEntryFilter($record);
        if ($filter && is_array($filter)) {
            $entries = $entries->filter($filter);
        };
        return $entries->first();
    }

    public function findOrMakeEntry($record)
    {
        $entry = $this->getEntry($record);
        if ($entry && $entry->exists()) {
            return $entry;
        }

        $class = $this->getEntryClass();
        $entry = $class::create();
        if ($class::singleton()->hasExtension(Versioned::class)) {
            if (Versioned::get_stage() === Versioned::LIVE) {
                $draftEntries = Versioned::get_by_stage(
                    static::class,
                    Versioned::DRAFT
                );
                $filter = $this->getEntryFilter($record);
                if ($filter && is_array($filter)) {
                    $draftEntries = $draftEntries->filter($filter);
                }
                $draftEntry = $draftEntries->first();
                if ($draftEntry && $draftEntry->exists()) {
                    $entry->ID = $draftEntry->ID;
                }
            }
        }
        return $entry;
    }

    public function addEntry($record)
    {
        if ($record->hasExtension(Versioned::class)) {
            if ($record->isPublished()) {

                $publishedRecord = Versioned::get_by_stage(
                    get_class($record),
                    Versioned::LIVE
                )->byID($record->ID);

                $this->writeEntry($publishedRecord);
                $this->publishEntry($publishedRecord);
            }
        }
        $this->writeEntry($record);
    }

    public function writeEntry($record)
    {
        $valid = $this->isValidRecord($record);
        if ($valid) {
            $entry = $this->loadRecord($record);
            $entry->write();
        }
        else {
            $this->deleteEntry($record);
        }
    }

    public function publishEntry($record)
    {
        $class = $this->getEntryClass();
        if (!$class::singleton()->hasExtension(Versioned::class)) {
            return;
        }
        $valid = $this->isValidRecord($record);
        if ($valid) {
            $entry = $this->loadRecord($record);
            $entry->copyVersionToStage(
                Versioned::DRAFT,
                Versioned::LIVE
            );
        }
        else {
            $this->unpublishEntry($record);
        }
    }

    public function unpublishEntry($record)
    {
        $class = $this->getEntryClass();
        if (!$class::singleton()->hasExtension(Versioned::class)) {
            return;
        }
        $entry = $this->getEntry($record);
        if ($entry && $entry->exists()) {
            $entry->doUnpublish();
        }
        else {
            $publishedEntries = Versioned::get_by_stage(
                $class,
                Versioned::LIVE
            );
            $filter = $this->getEntryFilter($record);
            if ($filter && is_array($filter)) {
                $publishedEntries = $publishedEntries->filter($filter);
            };
            if ($publishedEntries->count() > 0) {
                $publishedEntries->first()->doUnpublish();
            }
        }
    }

    public function deleteEntry($record)
    {
        $entry = $this->getEntry($record);
        if ($entry && $entry->exists()) {
            $entry->delete();
        }
    }

    public function isValidRecord($record)
    {
        return ($record->isInDB());
    }

    public function loadRecord($record, $entry = null)
    {
        if (!$entry) {
            $entry = $this->findOrMakeEntry($record);
        }
        return $entry;
    }

    public function getEntries()
    {
        $class = $this->getEntryClass();
        return $class::get()->filter('SearchEngineID', $this->ID);
    }

    public function getRecords($entries = null)
    {
        if (!$entries) {
            $entries = $this->getEntries();
        }
        $records = [];
        foreach ($entries as $entry) {
            $records[] = $entry->getRecord();
        }
        return ArrayList::create($records);
    }

    public function getTargetSearchPage()
    {
        if ($this->DefaultSearchPageID) {
            return $this->DefaultSearchPage();
        }
        $pages = $this->getAvailableSearchPages();
        if ($pages) {
            return $pages->first();
        }
        return null;
    }

    protected function isLogEnabled()
    {
        return ($this->config()->get('engine_log_enabled'));
    }

    protected function getEntryFilter($record)
    {
        return null;
    }

    protected function getDirectSearchFilter($phrase)
    {
        $config = $this->getEngineConfig('direct');
        return $this->buildFilter($config, $phrase);
    }

    protected function getSearchFilter($phrase)
    {
        $config = $this->getEngineConfig('fields');
        return $this->buildFilter($config, $phrase);
    }

    protected function buildFilter($config, $phrase)
    {
        if (!$config) {
            return null;
        }
        if (!is_array($config)) {
            throw new \UnexpectedValueException(
                'Engine configs must be an array on ' . static::class
            );
        }
        $filter = [];
        foreach ($config as $field) {
            $filter[$field] = $phrase;
        }
        if (count($filter) < 1) {
            $filter = null;
        }
        return $filter;
    }

    protected function getEngineConfig($key = null)
    {
        $config = $this->config()->get('engine_config');
        if (!$config) {
            throw new \UnexpectedValueException(
                '$engine_config must be set on ' . static::class
            );
        }
        if (!is_array($config)) {
            throw new \UnexpectedValueException(
                '$engine_config must be an array on ' . static::class
            );
        }
        if (
            !isset($config['direct'])
            && !isset($config['fields'])
        ) {
            throw new \UnexpectedValueException(
                'You must set at least one of $fields or $direct '
                . 'values in $engine_config on ' . static::class
            );
        }

        if ($key) {
            if (isset($config[$key])) {
                $config = $config[$key];
            }
            else {
                $config = null;
            }
        }

        $this->extend('updateEngineConfig', $config, $key);
        return $config;
    }

    public function getSortSQL($key)
    {
        if ($key === 'direct') {
            $mode = $this->DirectSortMode;
            $direction = $this->DirectSortDirection;
        }
        else if ($key === 'fields') {
            $mode = $this->SortMode;
            $direction = $this->SortDirection;
        }
        else {
            return null;
        }

        if (!$mode) {
            $mode = $this->getDefaultSortMode($key);
        }

        if ($mode) {
            $config = $this->getSortConfig($key, $mode);
            if (isset($config['sql'])) {
                $sql = $config['sql'];
                if ($direction) {
                    $sql .= ' ' . $direction;
                }
                return $sql;
            }
        }

        return null;
    }

    public function getDefaultSortMode($key)
    {
        return $this->getSortConfig($key, 'default');
    }

    public function getSortConfig($key, $mode)
    {
        $sortConfig = $this->getEngineSortConfig($key);
        if (!$sortConfig) {
            return null;
        }
        if (isset($sortConfig[$mode])) {
            return $sortConfig[$mode];
        }
        return null;
    }

    public function getSortFields($sortFieldName, $directionFieldName, $key)
    {
        $sortConfig = $this->getEngineSortConfig($key);
        if (!$sortConfig) {
            return null;
        }

        $sortSource = [];
        $directionsMap = [];

        foreach ($sortConfig as $mode => $settings) {
            if (strtolower($mode) === 'default') {
                continue;
            }
            $sortSource[$mode] = $settings['name'];
            if (isset($settings['direction'])) {
                $direction = strtolower($settings['direction']);
                if ($direction === 'asc') {
                    $directionsMap[$mode] = [
                        'ASC' => 'Ascending'
                    ];
                }
                else if ($direction === 'desc') {
                    $directionsMap[$mode] = [
                        'DESC' => 'Descending'
                    ];
                }
            }
        }

        $directionsSource = function($mode) use ($directionsMap) {
            if (isset($directionsMap[$mode])) {
                return $directionsMap[$mode];
            }
            return [
                'ASC' => 'Ascending',
                'DESC' => 'Descending'
            ];
        };

        $sortField = DropdownField::create(
            $sortFieldName,
            $this->fieldLabel($sortFieldName),
            $sortSource
        );
        $sortField->setHasEmptyDefault(false);

        if (isset($sortConfig['default'])) {
            $defaultValue = $sortConfig['default'];
            $sortField->setValue($defaultValue);
        }

        $directionsField = DependentDropdownField::create(
            $directionFieldName,
            $this->fieldLabel($directionFieldName),
            $directionsSource
        )->setDepends($sortField);

        return [$sortField, $directionsField];
    }

    protected function getEngineSortConfig($key = null)
    {
        $config = $this->config()->get('engine_sort_config');
        if (!$config) {
            return null;
        }
        if (!is_array($config)) {
            throw new \UnexpectedValueException(
                '$engine_sort_config must be an array on ' . static::class
            );
        }
        if (
            !isset($config['direct'])
            && !isset($config['fields'])
        ) {
            throw new \UnexpectedValueException(
                'You must set at least one of $fields or $direct '
                . 'values in $engine_sort_config on ' . static::class
            );
        }

        if ($key) {
            if (isset($config[$key])) {
                $config = $config[$key];
            }
            else {
                $config = null;
            }
        }

        $this->extend('updateEngineSortConfig', $config, $key);
        return $config;
    }

    protected function getAvailableSearchPages(bool $includeSubclasses = true)
    {
        if (!$this->isInDB()) {
            return null;
        }
        $classes = self::$search_page_classes;
        if (count($classes) < 1) {
            return null;
        }
        $pageIDs = [];
        $filter = ['SearchEngineID' => $this->ID];
        foreach ($classes as $class) {
            if (!$includeSubclasses) {
                $filter['ClassName'] = $class;
            }
            $pages = $class::get()->filter($filter);
            $pageIDs = array_merge($pageIDs, $pages->columnUnique('ID'));
            unset($filter['ClassName']);
        }
        if (count($pageIDs) < 1) {
            return null;
        }
        $commonClass = CommonAncestor::get_closest($classes);
        $searchPages = $commonClass::get()->filter('ID', $pageIDs);
        if ($searchPages->count() > 0) {
            return $searchPages;
        }
        return null;
    }

    protected function getEntryClass()
    {
        $class = $this->config()->get('engine_entry_class');
        if (!$class) {
            throw new \UnexpectedValueException(
                '$engine_entry_class must be set on ' . static::class
            );
        }
        if (!ClassInfo::exists($class)) {
            throw new \UnexpectedValueException(
                'A non-existent class "' . $class
                . '"has been set as $engine_entry_class on ' . static::class
            );
        }
        if (!ClassInfo::classImplements($class, SearchEngineEntry::class)) {
            throw new \UnexpectedValueException(
                'The class "' . $class
                . '"has been set as $engine_entry_class on ' . static::class
                . ' but does not implement ' . SearchEngineEntry::class
            );
        }
        return $class;
    }

    public function isConfigured()
    {
        try {
            $this->getEntryClass();
            $this->getEngineConfig();
            return true;

        } catch (\Exception $exception) {
            return false;
        }
    }
}
