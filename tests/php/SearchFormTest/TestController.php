<?php

namespace Fromholdio\Sherlock\Tests\SearchFormTest;

use Fromholdio\Sherlock\Forms\SearchForm;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Dev\TestOnly;

/**
 * Test-only controller serving a {@see SearchForm} for the test's search engine.
 *
 * Submissions go through the framework's own form handling, which validates the
 * required fields and then calls `SearchForm::doSearch()` with the raw request data.
 */
class TestController extends Controller implements TestOnly
{
    private static $url_segment = 'SearchFormTest_TestController';

    private static $allowed_actions = [
        'show',
        'SearchForm',
    ];

    /**
     * Render the form as a page would, with the source page ID from the URL.
     */
    public function show(HTTPRequest $request): string
    {
        $form = $this->createSearchForm((int) $request->param('ID'));

        //form actions are relative links, resolved against the base tag as on any page
        return '<!DOCTYPE html><html><head><base href="' . Director::absoluteBaseURL() . '"></head><body>'
            . $form->forTemplate()
            . '</body></html>';
    }

    public function SearchForm(): SearchForm
    {
        return $this->createSearchForm();
    }

    protected function createSearchForm(int $sourcePageID = 0): SearchForm
    {
        $engine = TestSearchEngine::get()->first();
        return SearchForm::create($this, 'SearchForm', (int) $engine->ID, $sourcePageID);
    }
}
