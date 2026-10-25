<?php

namespace Fromholdio\Sherlock\Tests;

use Fromholdio\Sherlock\Forms\SearchForm;
use Fromholdio\Sherlock\Tests\SearchFormTest\TestController;
use Fromholdio\Sherlock\Tests\SearchFormTest\TestSearchEngine;
use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Session;
use SilverStripe\Core\Validation\ValidationResult;
use SilverStripe\Dev\FunctionalTest;
use stdClass;

/**
 * The optional source page ID submitted with a {@see SearchForm}.
 *
 * A missing, null or empty ID means there is no source page. A valid ID reaches the
 * results redirect as an integer. Any other value fails the submission with a form
 * error and a redirect back, before the results link is built.
 *
 * Request data only ever holds strings and arrays, so those cases are posted through
 * the framework's form handling. The other types can only come from code calling
 * `doSearch()` directly, so they are passed that way.
 */
class SearchFormTest extends FunctionalTest
{
    protected $usesDatabase = true;

    protected static $extra_dataobjects = [
        TestSearchPage::class,
        TestSearchEngine::class,
    ];

    protected static $extra_controllers = [
        TestController::class,
    ];

    protected static $disable_themes = true;

    protected $autoFollowRedirection = false;

    protected const REFERER = 'https://www.example.com/start';

    protected const SEARCH_PAGE_LINK = 'https://www.example.com/search';

    protected function setUp(): void
    {
        parent::setUp();
        Director::config()->set('alternate_base_url', 'https://www.example.com/');
        Controller::config()->set('add_trailing_slash', false);

        $page = TestSearchPage::create();
        $page->Title = 'Search';
        $page->URLSegment = 'search';
        $page->write();
        //front-end requests read the live stage
        $page->publishRecursive();

        $engine = TestSearchEngine::create();
        $engine->Title = 'Test engine';
        $engine->DefaultSearchPageID = $page->ID;
        $engine->write();

        TestSearchEngine::$linkCalls = [];
    }

    public static function provideValidSubmittedIDs(): array
    {
        return [
            'positive ID' => ['42', 42, '?q=widget&p=42'],
            'one' => ['1', 1, '?q=widget&p=1'],
            'zero' => ['0', 0, '?q=widget'],
            'empty' => ['', 0, '?q=widget'],
            'largest integer' => [(string) PHP_INT_MAX, PHP_INT_MAX, '?q=widget&p=' . PHP_INT_MAX],
        ];
    }

    #[DataProvider('provideValidSubmittedIDs')]
    public function testValidSubmittedIDRedirectsToResults(string $submitted, int $expectedID, string $expectedQuery): void
    {
        $response = $this->postSearch(['SourcePageID' => $submitted]);

        $this->assertRedirectedToResults($response, $expectedID, $expectedQuery);
    }

    public function testMissingIDRedirectsToResultsWithoutSourcePage(): void
    {
        $response = $this->postSearch([]);

        $this->assertRedirectedToResults($response, 0, '?q=widget');
    }

    public static function provideRenderedSourcePages(): array
    {
        return [
            'no source page' => [0, '?q=widget'],
            'source page' => [42, '?q=widget&p=42'],
        ];
    }

    /**
     * The hidden field of a form built without a source page renders with no value,
     * so the browser submits an empty string.
     */
    #[DataProvider('provideRenderedSourcePages')]
    public function testRenderedFormSubmitsItsSourcePage(int $sourcePageID, string $expectedQuery): void
    {
        $this->get(TestController::singleton()->Link('show/' . $sourcePageID));
        $formName = TestController::singleton()->SearchForm()->FormName();
        $response = $this->submitForm($formName, 'action_doSearch', ['Phrase' => 'widget']);

        $this->assertRedirectedToResults($response, $sourcePageID, $expectedQuery);
    }

    public static function provideInvalidSubmittedIDs(): array
    {
        //PHP_INT_MAX ends in 7 on 32-bit and 64-bit builds, so this is one more than it
        $overflow = substr((string) PHP_INT_MAX, 0, -1) . '8';

        return [
            'negative' => ['-1'],
            'fractional' => ['4.2'],
            'trailing garbage' => ['42garbage'],
            'non-numeric' => ['abc'],
            'exponent' => ['1e3'],
            'hexadecimal' => ['0x2A'],
            'plus sign' => ['+42'],
            'leading zero' => ['042'],
            'leading whitespace' => [' 42'],
            'trailing whitespace' => ['42 '],
            'whitespace only' => [' '],
            'overflow' => [$overflow],
            'far overflow' => ['99999999999999999999999999'],
            'array' => [['42']],
            'array holding an empty string' => [['']],
            'keyed array' => [['id' => '42']],
        ];
    }

    #[DataProvider('provideInvalidSubmittedIDs')]
    public function testInvalidSubmittedIDIsRejected(string|array $submitted): void
    {
        $response = $this->postSearch(['SourcePageID' => $submitted]);

        $this->assertRejected($response, $this->getSessionResult());
    }

    public static function provideValidDirectIDs(): array
    {
        return [
            'integer' => [42, 42, '?q=widget&p=42'],
            'zero integer' => [0, 0, '?q=widget'],
            'null' => [null, 0, '?q=widget'],
        ];
    }

    #[DataProvider('provideValidDirectIDs')]
    public function testValidDirectIDRedirectsToResults(?int $sourcePageID, int $expectedID, string $expectedQuery): void
    {
        [$response] = $this->callDoSearch(['SourcePageID' => $sourcePageID]);

        $this->assertRedirectedToResults($response, $expectedID, $expectedQuery);
    }

    public static function provideInvalidDirectIDs(): array
    {
        return [
            'negative integer' => [-1],
            'fractional float' => [4.2],
            'whole float' => [42.0],
            'true' => [true],
            'false' => [false],
            'object' => [new stdClass()],
            'stringable object' => [new class () {
                public function __toString(): string
                {
                    return '42';
                }
            }],
        ];
    }

    #[DataProvider('provideInvalidDirectIDs')]
    public function testInvalidDirectIDIsRejected(mixed $sourcePageID): void
    {
        [$response, $form] = $this->callDoSearch(['SourcePageID' => $sourcePageID]);

        $this->assertRejected($response, $form->getSessionValidationResult());
    }

    protected function postSearch(array $data): HTTPResponse
    {
        $data = array_merge(
            [
                'EngineID' => (string) TestSearchEngine::get()->first()->ID,
                'Phrase' => 'widget',
                'action_doSearch' => 'Search',
            ],
            $data
        );

        return $this->post(
            TestController::singleton()->Link('SearchForm'),
            $data,
            ['Referer' => static::REFERER]
        );
    }

    /**
     * @return array{HTTPResponse, SearchForm}
     */
    protected function callDoSearch(array $data): array
    {
        $data = array_merge(
            [
                'EngineID' => TestSearchEngine::get()->first()->ID,
                'Phrase' => 'widget',
            ],
            $data
        );

        $request = new HTTPRequest('POST', TestController::singleton()->Link('SearchForm'), [], $data);
        $request->addHeader('Referer', static::REFERER);
        $request->setSession(new Session([]));
        $controller = TestController::create();
        $controller->setRequest($request);
        $form = $controller->SearchForm();

        return [$form->doSearch($data, $form, $request), $form];
    }

    protected function getSessionResult(): ?ValidationResult
    {
        $formName = TestController::singleton()->SearchForm()->FormName();
        $resultData = $this->session()->get("FormInfo.{$formName}.result");
        return isset($resultData) ? unserialize($resultData) : null;
    }

    protected function assertRedirectedToResults(HTTPResponse $response, int $expectedID, string $expectedQuery): void
    {
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(static::SEARCH_PAGE_LINK . $expectedQuery, $response->getHeader('Location'));
        $this->assertSame([['widget', $expectedID]], TestSearchEngine::$linkCalls);
    }

    protected function assertRejected(HTTPResponse $response, ?ValidationResult $result): void
    {
        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame(static::REFERER, $response->getHeader('Location'));
        $this->assertSame([], TestSearchEngine::$linkCalls, 'The results link was built for an invalid ID');

        $this->assertNotNull($result);
        $this->assertFalse($result->isValid());
        $this->assertSame(['Invalid Source Page ID'], array_column($result->getMessages(), 'message'));
    }
}
