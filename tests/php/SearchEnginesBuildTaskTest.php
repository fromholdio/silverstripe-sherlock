<?php

namespace Fromholdio\Sherlock\Tests;

use Fromholdio\Sherlock\Tasks\SearchEnginesBuildTask;
use SilverStripe\Dev\SapphireTest;

class SearchEnginesBuildTaskTest extends SapphireTest
{
    public function testTaskIdentity(): void
    {
        $this->assertSame('Build Search Engine Manifests', SearchEnginesBuildTask::create()->getTitle());
        $this->assertSame(
            'Only needs to be called once on production, or to re-build the search engines',
            SearchEnginesBuildTask::getDescription()
        );
        $this->assertSame('tasks:search-manifest-build', SearchEnginesBuildTask::getName());
    }
}
