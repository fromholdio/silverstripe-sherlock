<?php

namespace Fromholdio\Sherlock;

use Fromholdio\Sherlock\Model\SearchEngine;
use Fromholdio\Sherlock\Model\SearchLog;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\ORM\SS_List;
use SilverStripe\Versioned\Versioned;

class SearchAction
{
    use Injectable;
    use Extensible;
    use Configurable;

    protected $phrase;
    protected $engine;
    protected $directResult;
    protected $results;
    protected $startTime;
    protected $endTime;
    protected $searchPageID;
    protected $log;
    protected $logEnabled;

    public function __construct(
        string $phrase,
        SearchEngine $engine,
        int $searchPageID = null,
        bool $logEnabled = true,
        bool $doSearchImmediately = true
    ) {
        $this->setPhrase($phrase);
        $this->setEngine($engine);
        $this->setSearchPageID($searchPageID);
        $this->setLogEnabled($logEnabled);

        if ($doSearchImmediately) {
            $this->doSearch();
        }
    }

    public function doSearch()
    {
        $phrase = $this->getPhrase();
        $engine = $this->getEngine();

        if ($this->getLogEnabled()) {
            $this->setStartTime();
        }

        $directResult = $engine->getDirectSearchResult($phrase);
        if ($directResult) {
            $this->setDirectResult($directResult);
        } else {
            $results = $engine->getSearchResults($phrase);
            if ($results) {
                $this->setResults($results);
            }
        }

        if ($this->getLogEnabled()) {
            $this->setEndTime();
            $this->writeLog();
        }
    }

    protected function writeLog()
    {
        if ($this->getLog() || !$this->getLogEnabled()) {
            return null;
        }
        $log = SearchLog::create();
        $log->Phrase = $this->getPhrase();
        $log->SearchEngineID = $this->getEngine()->ID;
        $log->HasDirectResult = $this->hasDirectResult();
        $log->ResultsCount = $this->getResultsCount();
        $log->Duration = $this->getDuration();
        $log->Stage = Versioned::get_stage();
        $log->SearchPageID = $this->getSearchPageID();
        $log->write();
        $this->setLog($log);
        return $log;
    }

    public function setLog(SearchLog $log)
    {
        if (!$this->getLogEnabled()) {
            return $this;
        }
        $this->log = $log;
        return $this;
    }

    public function getLog()
    {
        if (!$this->getLogEnabled()) {
            return null;
        }
        return $this->log;
    }

    public function getDuration()
    {
        $start = $this->getStartTime();
        $end = $this->getEndTime();
        return $end - $start;
    }

    public function setLogEnabled($value)
    {
        $this->logEnabled = ($value);
        return $this->logEnabled;
    }

    public function getLogEnabled()
    {
        return ($this->logEnabled);
    }

    public function setEngine(SearchEngine $engine)
    {
        $this->engine = $engine;
        return $this;
    }

    public function getEngine()
    {
        return $this->engine;
    }

    public function setSearchPageID(int $pageID = null)
    {
        $this->searchPageID = $pageID;
        return $this;
    }

    public function getSearchPageID()
    {
        return $this->searchPageID;
    }

    public function setPhrase($value)
    {
        $this->phrase = $value;
        return $this;
    }

    public function getPhrase()
    {
        return $this->phrase;
    }

    public function setStartTime($value = null)
    {
        if (!$value) {
            $value = microtime(true);
        }
        $this->startTime = $value;
        return $this;
    }

    public function getStartTime()
    {
        $startTime = $this->startTime;
        if (!$startTime) {
            $startTime = $this->setStartTime();
        }
        return $startTime;
    }

    public function setEndTime($value = null)
    {
        if (!$value) {
            $value = microtime(true);
        }
        $this->endTime = $value;
        return $this;
    }

    public function getEndTime()
    {
        $endTime = $this->endTime;
        if (!$endTime) {
            $endTime = $this->setEndTime();
        }
        return $endTime;
    }

    public function hasDirectResult()
    {
        return (bool) $this->directResult;
    }

    public function getDirectResultLink()
    {
        $result = $this->getDirectResult();
        if ($result && $result->hasMethod('Link')) {
            return $result->Link();
        }
        return null;
    }

    public function setDirectResult($result = null)
    {
        $this->directResult = $result;
        return $this;
    }

    public function getDirectResult()
    {
        return $this->directResult;
    }

    public function hasResults()
    {
        return $this->getResultsCount() > 0;
    }

    public function getResultsCount()
    {
        if ($this->hasDirectResult()) {
            return 1;
        }
        $results = $this->getResults();
        if ($results) {
            return $results->count();
        }
        return 0;
    }

    public function setResults($results = null)
    {
        if ($results !== null && !ClassInfo::classImplements(get_class($results), SS_List::class)) {
            throw new \InvalidArgumentException(
                'SearchResult::setResults($results) expects $results to be NULL or '
                . 'an implmentor of ' . SS_List::class . '. Instead received ' . get_class($results)
            );
        }
        $this->results = $results;
        return $this;
    }

    public function getResults()
    {
        $results = $this->results;
        return $results;
    }
}
