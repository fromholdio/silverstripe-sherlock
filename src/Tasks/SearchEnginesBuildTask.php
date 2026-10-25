<?php

namespace Fromholdio\Sherlock\Tasks;

use Fromholdio\Sherlock\Extensions\SearchRecordExtension;
use Fromholdio\Sherlock\Model\SearchEngine;
use Override;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\BuildTask;
use SilverStripe\ORM\DataObject;
use SilverStripe\PolyExecution\PolyOutput;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;

class SearchEnginesBuildTask extends BuildTask
{
    protected string $title = "Build Search Engine Manifests";

    protected static string $description = "Only needs to be called once on production, or to re-build the search engines";

    protected static string $commandName = 'search-manifest-build';

    #[Override]
    protected function execute(InputInterface $input, PolyOutput $output): int
    {
        set_time_limit(0);
        $allDataObjectClasses = ClassInfo::subclassesFor(DataObject::class);
        $recordClasses = [];
        foreach ($allDataObjectClasses as $className) {
            if ($className::has_extension(SearchRecordExtension::class)) {
                $recordClasses[$className] = $className;
            }
        }

        if (count($recordClasses) < 1) {
            $this->outputText('No models implement SearchRecordExtension. Nothing to do...');
            exit;
        }

        $hasConfiguredEngine = false;
        $engines = SearchEngine::get();
        foreach ($engines as $engine) {
            if ($engine->isConfigured()) {
                $hasConfiguredEngine = true;
                break;
            }
        }

        if (!$hasConfiguredEngine) {
            $this->outputText('No Search Engines are configured. Nothing to do...');
            exit;
        }

        $this->outputText('');
        $this->outputText('');
        $this->outputText('# START TO POPULATE ENGINES');
        foreach ($recordClasses as $recordClass) {
            $this->runOnClass($recordClass);
        }

        $this->outputText('');
        $this->outputText('');
        $this->outputText('# COMPLETED POPULATE ENGINES');
        return Command::SUCCESS;
    }

    public function runOnClass($className): void
    {
        $this->outputText('');
        $this->outputText('');
        $this->outputText('# START ' . $className);

        $records = $className::get();

        if (!$records || $records->count() < 1) {
            $this->outputText('This model has no records...');
            return;
        }

        foreach ($records as $record) {
            $record->addToSearchEngines();
            $this->outputText('  - "' . $record->getTitle() . '" added');
        }

        $this->outputText('');
        $this->outputText('');
        $this->outputText('#### COMPLETED ' . $className);
    }

    public function outputText($text, $break = true): void
    {
        if ($break) {
            $break = "<br>";

            if (Director::is_cli()) {
                $break = PHP_EOL;
            }

            echo $text . $break;
            return;
        }

        echo $text;
    }
}
