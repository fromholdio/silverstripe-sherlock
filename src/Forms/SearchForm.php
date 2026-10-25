<?php

namespace Fromholdio\Sherlock\Forms;

use Fromholdio\Sherlock\Model\SearchEngine;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\RequestHandler;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\TextField;
use SilverStripe\Forms\Validation\RequiredFieldsValidator;

class SearchForm extends Form
{
    protected $engine;

    public function __construct(
        ?RequestHandler $controller,
        $name,
        int $engineID,
        int $sourcePageID = 0,
        $phraseTitle = 'Search',
        $actionTitle = 'Search',
        $phrasePlaceholder = null
    ) {
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

    public function setPhraseFieldTitle($value): static
    {
        $phraseField = $this->Fields()->fieldByName('Phrase');
        if ($phraseField) {
            $phraseField->setTitle($value);
        }

        return $this;
    }

    public function setPhraseFieldPlaceholder($value): static
    {
        $phraseField = $this->Fields()->fieldByName('Phrase');
        if ($phraseField) {
            $phraseField->setAttribute('placeholder', $value);
        }

        return $this;
    }

    public function setEngineID(int $engineID): static
    {
        $engineIDField = $this->Fields()->fieldByName('EngineID');
        if ($engineIDField) {
            $engineIDField->setValue($engineID);
        }

        return $this;
    }

    public function setSourcePageID(int $pageID = 0): static
    {
        $sourcePageIDField = $this->Fields()->fieldByName('SourcePageID');
        if ($sourcePageIDField) {
            $sourcePageIDField->setValue($pageID);
        }

        return $this;
    }

    public function setSearchActionTitle($value): static
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
        return FieldList::create(
            FormAction::create('doSearch', 'Search')
                ->setUseButtonTag(true)
        );
    }

    protected function getFormValidator()
    {
        return RequiredFieldsValidator::create('Phrase', 'EngineID');
    }

    public function doSearch(array $data, SearchForm $form, $request): HTTPResponse
    {
        $engineID = $data['EngineID'];
        $engine = SearchEngine::get()->byID($engineID);
        if (!$engine || !$engine->exists()) {
            $form->sessionError('Invalid Search Engine ID');
            return $this->getController()->redirectBack();
        }

        $phrase = $data['Phrase'];

        $sourcePageID = $this->getSubmittedSourcePageID($data);
        if (is_null($sourcePageID)) {
            $form->sessionError('Invalid Source Page ID');
            return $this->getController()->redirectBack();
        }

        $searchLink = $engine->Link($phrase, $sourcePageID);
        return $this->getController()->redirect($searchLink);
    }

    /**
     * Get the source page ID from submitted form data, where 0 means no source page.
     *
     * A missing, null or empty value gives 0. Otherwise the value must be a
     * non-negative integer, or a string of digits without a sign, whitespace or
     * leading zeros (other than "0" itself) that fits in an integer, as the hidden
     * field renders it. Any other value gives null, so the submission can be rejected.
     */
    protected function getSubmittedSourcePageID(array $data): ?int
    {
        $value = $data['SourcePageID'] ?? null;
        if (is_null($value) || $value === '') {
            return 0;
        }

        if (is_int($value)) {
            return $value >= 0 ? $value : null;
        }

        if (!is_string($value) || !preg_match('/\A(?:0|[1-9][0-9]*)\z/', $value)) {
            return null;
        }

        //digits beyond PHP_INT_MAX fail here rather than being clamped to it
        $sourcePageID = filter_var($value, FILTER_VALIDATE_INT);
        return $sourcePageID === false ? null : $sourcePageID;
    }
}
