<?php
namespace Concrete\Package\TranslationUtilities;

defined('C5_EXECUTE') or die(_("Access Denied."));

use Core;
use Package;

class Controller extends Package
{

    protected $pkgHandle = 'translation_utilities';
    protected $appVersionRequired = '5.7.4';
    protected $pkgVersion = '0.0.1';

    public function getPackageName()
    {
        return t("Translation Utilities");
    }

    public function getPackageDescription()
    {
        return t("Utilities for handling translations.");
    }

    public function on_start()
    {
        $app = Core::getFacadeApplication();
        if ($app->bound('console')) {
            $cli = $app->make('console');
            $cli->add(new \Concrete\Package\TranslationUtilities\Src\Command\ExtractTranslationsCommand());
        }
    }

}