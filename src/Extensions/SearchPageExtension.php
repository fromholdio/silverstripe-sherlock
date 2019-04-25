<?php

namespace Fromholdio\Sherlock\Extensions;

use Fromholdio\Sherlock\Model\SearchEngine;
use SilverStripe\CMS\Model\SiteTreeExtension;
use SilverStripe\Control\Controller;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\ReadonlyField;

class SearchPageExtension extends SiteTreeExtension
{
    private static $allowed_search_engines;
    private static $search_get_var_name = 'q';
    private static $search_source_get_var_name = 'p';
    private static $search_engine_field_insert_after = 'MenuTitle';

    private static $has_one = [
        'SearchEngine' => SearchEngine::class
    ];

    private static $defaults = [
        'ShowInSearch' => 0
    ];

    public static function add_to_class($class, $extensionClass, $args = null)
    {
        SearchEngine::register_search_page_class($class);
    }

    public function SearchLink($searchPhrase = null, int $sourcePageID = null)
    {
        $getVarName = $this->getOwner()->getSearchGetVarName();
        $queryString = '?' . $getVarName . '=' . urlencode($searchPhrase);
        if ($sourcePageID && $sourcePageID > 0) {
            $sourceGetVarName = $this->getOwner()->getSearchSourceGetVarName();
            $queryString .= '&' . $sourceGetVarName . '=' . urlencode($sourcePageID);
        }
        return Controller::join_links(
            $this->getOwner()->Link(),
            $queryString
        );
    }

    public function AbsoluteSearchLink($searchPhrase = null, int $sourcePageID = null)
    {
        $getVarName = $this->getOwner()->getSearchGetVarName();
        $queryString = '?' . $getVarName . '=' . urlencode($searchPhrase);
        if ($sourcePageID && $sourcePageID > 0) {
            $sourceGetVarName = $this->getOwner()->getSearchSourceGetVarName();
            $queryString .= '&' . $sourceGetVarName . '=' . urlencode($sourcePageID);
        }
        return Controller::join_links(
            $this->getOwner()->AbsoluteLink(),
            $queryString
        );
    }

    public function updateCMSFields(FieldList $fields)
    {
        $fields->removeByName('SearchEngineID');
        $allowedEngines = $this->getOwner()->getAllowedSearchEngines();
        if ($allowedEngines) {
            $engineField = DropdownField::create(
                'SearchEngineID',
                $this->getOwner()->fieldLabel('SearchEngine'),
                $allowedEngines->map()->toArray()
            );
            $engineField->setHasEmptyDefault(false);
        }
        else {
            $engineField = ReadonlyField::create(
                'NoSearchEnginesAllowed',
                $this->getOwner()->fieldLabel('SearchEngineID'),
                'A search engine must be configured before you can select it here.'
            );
        }
        $insertAfter = $this->getOwner()->config()->get('search_engine_field_insert_after');
        $fields->insertAfter($insertAfter, $engineField);
    }

    public function updateSettingsFields(FieldList $fields)
    {
        $fields->removeByName('ShowInSearch');
    }

    public function getSearchGetVarName()
    {
        $varName = $this->getOwner()->config()->get('search_get_var_name');
        if (!$varName) {
            throw new \UnexpectedValueException(
                'You must set $search_get_var_name on ' . ClassInfo::class_name($this->getOwner())
            );
        }
        return $varName;
    }

    public function getSearchSourceGetVarName()
    {
        $varName = $this->getOwner()->config()->get('search_source_get_var_name');
        if (!$varName) {
            throw new \UnexpectedValueException(
                'You must set $search_source_get_var_name on ' . ClassInfo::class_name($this->getOwner())
            );
        }
        return $varName;
    }

    public function getAllowedSearchEngines()
    {
        $classes = $this->getOwner()->getAllowedSearchEngineClasses();
        if (!$classes) {
            return null;
        }
        $engines = SearchEngine::get()->filter('ClassName', $classes);
        if ($engines->count() > 0) {
            return $engines;
        }
        return null;
    }

    public function getAllowedSearchEngineClasses()
    {
        $allowedEngineClasses = $this->getOwner()->config()->get('allowed_search_engines');
        if ($allowedEngineClasses && !is_array($allowedEngineClasses)) {
            throw new \UnexpectedValueException(
                '$allowed_search_engines must be an array on '
                . ClassInfo::class_name($this->getOwner())
            );
        }
        if (is_array($allowedEngineClasses) && count($allowedEngineClasses) > 0) {
            foreach ($allowedEngineClasses as $engineClass) {
                if (!is_a($engineClass, SearchEngine::class, true)) {
                    throw new \UnexpectedValueException(
                        'Invalid class value in $allowed_search_engines on '
                        . ClassInfo::class_name($this->getOwner())
                        . '. Class must be sub-class of ' . SearchEngine::class
                        . '. Invalid value supplied was "' . $engineClass . '"'
                    );
                }
            }
            return $allowedEngineClasses;
        }
        $allEngineClasses = ClassInfo::subclassesFor(SearchEngine::class);
        if (isset($allEngineClasses[strtolower(SearchEngine::class)])) {
            unset($allEngineClasses[strtolower(SearchEngine::class)]);
        }
        if (count($allEngineClasses) > 0) {
            return array_values($allEngineClasses);
        }
        return null;
    }
}
