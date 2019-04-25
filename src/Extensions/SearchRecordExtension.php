<?php

namespace Fromholdio\Sherlock\Extensions;

use Fromholdio\Sherlock\Model\SearchEngine;
use SilverStripe\Core\ClassInfo;
use SilverStripe\ORM\DataExtension;
use SilverStripe\Versioned\Versioned;

class SearchRecordExtension extends DataExtension
{
    public function addToSearchEngines()
    {
        $engines = $this->getOwner()->getSearchEngines();
        if ($engines) {
            foreach ($engines as $engine) {
                $engine->addEntry($this->getOwner());
            }
        }
    }

    public function onAfterWrite()
    {
        if ($this->getOwner()->hasExtension(Versioned::class)) {
            if (Versioned::get_stage() === Versioned::LIVE) {
                return;
            }
        }
        $engines = $this->getOwner()->getSearchEngines();
        if ($engines) {
            foreach ($engines as $engine) {
                $engine->writeEntry($this->getOwner());
            }
        }
    }

    public function onAfterPublish()
    {
        $engines = $this->getOwner()->getSearchEngines();
        if ($engines) {
            foreach ($engines as $engine) {
                $engine->publishEntry($this->getOwner());
            }
        }
    }

    public function onAfterDelete()
    {
        if ($this->getOwner()->hasExtension(Versioned::class)) {
            if (Versioned::get_stage() === Versioned::LIVE) {
                return;
            }
        }
        $engines = $this->getOwner()->getSearchEngines();
        if ($engines) {
            foreach ($engines as $engine) {
                $engine->deleteEntry($this->getOwner());
            }
        }
    }

    public function onAfterUnpublish()
    {
        $engines = $this->getOwner()->getSearchEngines();
        if ($engines) {
            foreach ($engines as $engine) {
                $engine->unpublishEntry($this->getOwner());
            }
        }
    }

    public function getSearchEngines()
    {
        $classes = $this->getOwner()->getSearchEngineClasses();
        $classNameFilter = [];
        foreach ($classes as $class) {
            $classNameFilter = array_merge(
                $classNameFilter,
                ClassInfo::subclassesFor($class)
            );
        }
        $engines = SearchEngine::get()->filter([
            'ClassName' => array_values($classNameFilter)
        ]);
        if ($engines->count() > 0) {
            return $engines;
        }
        return null;
    }

    public function getSearchEngineClasses()
    {
        $engineClasses = $this->getOwner()->config()->get('search_engine_classes');
        if (!$engineClasses) {
            throw new \UnexpectedValueException(
                '$search_engine_classes must be defined on '
                . ClassInfo::class_name($this->getOwner())
            );
        }
        if (!is_array($engineClasses)) {
            throw new \UnexpectedValueException(
                '$search_engine_classes must be an array on '
                . ClassInfo::class_name($this->getOwner())
            );
        }
        if (count($engineClasses) < 1) {
            throw new \UnexpectedValueException(
                '$search_engine_classes must be an array with at least one value on '
                . ClassInfo::class_name($this->getOwner())
            );
        }
        if (count($engineClasses) > 0) {
            foreach ($engineClasses as $engineClass) {
                if (!is_a($engineClass, SearchEngine::class, true)) {
                    throw new \UnexpectedValueException(
                        'Invalid class value in $search_engines_classes on '
                        . ClassInfo::class_name($this->getOwner())
                        . '. Class must be sub-class of ' . SearchEngine::class
                        . '. Invalid value supplied was "' . $engineClass . '"'
                    );
                }
            }
        }
        return $engineClasses;
    }
}
