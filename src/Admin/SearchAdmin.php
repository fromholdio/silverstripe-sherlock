<?php

namespace Fromholdio\Sherlock\Admin;

use Fromholdio\Sherlock\Model\SearchEngine;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Forms\GridField\GridFieldAddExistingAutocompleter;
use SilverStripe\Forms\GridField\GridFieldAddNewButton;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use Symbiote\GridFieldExtensions\GridFieldAddNewMultiClass;

class SearchAdmin extends ModelAdmin
{
    private static $managed_models = [SearchEngine::class];
    private static $url_segment = 'search';
    private static $menu_title = 'Search';
    private static $menu_icon_class = 'font-icon-search';

    public $showImportForm = false;
    public $showSearchForm = false;

    public function getEditForm($id = null, $fields = null)
    {
        $form = parent::getEditForm($id, $fields);

        $field = $form->Fields()->fieldByName(
            $this->sanitiseClassName($this->modelClass)
        );
        $config = $field->getConfig();
        $config->removeComponentsByType([
            GridFieldExportButton::class,
            GridFieldPrintButton::class
        ]);

        if ($this->modelClass === SearchEngine::class) {
            $config->removeComponentsByType([
                GridFieldAddExistingAutocompleter::class,
                GridFieldAddNewButton::class
            ]);
            $adderSource = [];
            $engineClasses = ClassInfo::subclassesFor($this->modelClass);
            foreach ($engineClasses as $engineClass) {
                if ($engineClass !== SearchEngine::class) {
                    $engineSingleton = $engineClass::singleton();
                    if ($engineSingleton->canCreate()) {
                        $adderSource[$engineClass] = $engineSingleton->i18n_singular_name();
                    }
                }
            }
            if (count($adderSource) > 0) {
                $multiAdder = new GridFieldAddNewMultiClass();
                $multiAdder->setClasses($adderSource);
                $config->addComponent($multiAdder);
            }
        }

        return $form;
    }
}
