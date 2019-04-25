<?php

namespace Fromholdio\Sherlock\Forms;

use Fromholdio\Sherlock\Model\SearchEngine;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\Forms\TextField;

class SearchForm extends Form
{
    protected $engine;

    public function __construct(
        $controller,
        $name,
        int $engineID,
        int $sourcePageID = 0,
        $phraseTitle = 'Search',
        $actionTitle = 'Search',
        $phrasePlaceholder = null
    ){
        parent::__construct(
            $controller,
            $name,
            $this->getFormFields(),
            $this->getFormActions(),
            $this->getFormValidator()
        );

        $this->setEngineID($engineID);
        if ($sourcePageID) {
            $this->setSourcePageID($sourcePageID);
        }
        $this->setPhraseFieldTitle($phraseTitle);
        $this->setSearchActionTitle($actionTitle);
        if ($phrasePlaceholder) {
            $this->setPhraseFieldPlaceholder($phrasePlaceholder);
        }
    }

    public function setPhraseFieldTitle($value)
    {
        $phraseField = $this->Fields()->fieldByName('Phrase');
        if ($phraseField) {
            $phraseField->setTitle($value);
        }
        return $this;
    }

    public function setPhraseFieldPlaceholder($value)
    {
        $phraseField = $this->Fields()->fieldByName('Phrase');
        if ($phraseField) {
            $phraseField->setAttribute('placeholder', $value);
        }
        return $this;
    }

    public function setEngineID(int $engineID)
    {
        $engineIDField = $this->Fields()->fieldByName('EngineID');
        if ($engineIDField) {
            $engineIDField->setValue($engineID);
        }
        return $this;
    }

    public function setSourcePageID(int $pageID = 0)
    {
        $sourcePageIDField = $this->Fields()->fieldByName('SourcePageID');
        if ($sourcePageIDField) {
            $sourcePageIDField->setValue($pageID);
        }
        return $this;
    }

    public function setSearchActionTitle($value)
    {
        $searchAction = $this->Actions()->fieldByName('action_doSearch');
        if ($searchAction) {
            $searchAction->setTitle($value);
        }
        return $this;
    }

    protected function getFormFields()
    {
        $phraseField = TextField::create('Phrase');
        $engineField = HiddenField::create('EngineID');
        $sourcePageIDField = HiddenField::create('SourcePageID');

        return FieldList::create(
            $phraseField,
            $engineField,
            $sourcePageIDField
        );
    }

    protected function getFormActions()
    {
        $actions = FieldList::create(
            FormAction::create('doSearch', 'Search')
                ->setUseButtonTag(true)
        );

        return $actions;
    }

    protected function getFormValidator()
    {
        $validator = RequiredFields::create('Phrase', 'EngineID');
        return $validator;
    }

    public function doSearch($data, SearchForm $form, $request)
    {
        $engineID = $data['EngineID'];
        $engine = SearchEngine::get()->byID($engineID);
        if (!$engine || !$engine->exists()) {
            $form->sessionError('Invalid Search Engine ID');
            return $this->getController()->redirectBack();
        }

        $phrase = $data['Phrase'];

        if (isset($data['SourcePageID'])) {
            $sourcePageID = $data['SourcePageID'];
        }
        else {
            $sourcePageID = 0;
        }

        $searchLink = $engine->Link($phrase, $sourcePageID);
        return $this->getController()->redirect($searchLink);
    }
}
