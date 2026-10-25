<?php

namespace Fromholdio\Sherlock\Tests;

use Fromholdio\Sherlock\Admin\SearchAdmin;
use SilverStripe\Dev\SapphireTest;

class SearchAdminTest extends SapphireTest
{
    public function testImportAndSearchFormsAreHidden(): void
    {
        $admin = SearchAdmin::create();

        $this->assertFalse($admin->showImportForm);
        $this->assertFalse($admin->showSearchForm);
    }
}
