<?php

namespace Fromholdio\Sherlock\Extensions;

use Fromholdio\Sherlock\SearchAction;
use SilverStripe\Core\Extension;

class SearchControllerExtension extends Extension
{
    public function onBeforeInit()
    {
        $getVarName = $this->getOwner()->data()->getSearchGetVarName();
        $phrase = $this->getOwner()->getRequest()->getVar($getVarName);
        if ($phrase) {
            $this->getOwner()->setSearchPhrase(urldecode($phrase));
            $sourceGetVarName = $this->getOwner()->data()->getSearchSourceGetVarName();
            $sourcePageID = $this->getOwner()->getRequest()->getVar($sourceGetVarName);
            if ($sourcePageID && $sourcePageID > 0) {
                $sourcePageID = (int) urldecode($sourcePageID);
                $this->getOwner()->setSearchSourcePageID($sourcePageID);
            }
        }
    }

    public function onAfterInit()
    {
        $this->getOwner()->getSearchResults();
    }

    public function setSearchPhrase($value)
    {
        $this->getOwner()->searchPhrase = trim($value);
        return $this->getOwner();
    }

    public function getSearchPhrase()
    {
        return $this->getOwner()->searchPhrase;
    }

    public function setSearchSourcePageID(int $value)
    {
        $this->getOwner()->searchSourcePageID = $value;
        return $this->getOwner();
    }

    public function getSearchSourcePageID()
    {
        return $this->getOwner()->searchSourcePageID;
    }

    public function setSearchAction(SearchAction $search = null)
    {
        $this->getOwner()->searchAction = $search;
        return $this->getOwner();
    }

    public function getSearchAction()
    {
        return $this->getOwner()->searchAction;
    }

    public function getSearchResults()
    {
        $search = $this->getOwner()->getSearchAction();
        if (!$search) {
            $phrase = $this->getOwner()->getSearchPhrase();
            if (!$phrase) {
                return null;
            }
            $sourcePageID = $this->getOwner()->getSearchSourcePageID();
            $engine = $this->getOwner()->data()->SearchEngine();
            if (!$engine || !$engine->exists()) {
                return null;
            }
            $search = $engine->search($phrase, $sourcePageID);
            $this->getOwner()->setSearchAction($search);
        }

        $directLink = $search->getDirectResultLink();
        if ($directLink) {
            return $this->getOwner()->redirect($directLink);
        }

        return $search->getResults();
    }
}
