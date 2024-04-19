<?php

declare(strict_types=1);

namespace App;

class Application extends \Hyde\Foundation\Application
{
    final public const APP_VERSION = '0.7.38';

    public function getCachedPackagesPath(): string
    {
        // Since we have a custom path for the cache directory, we need to return it here.
        return HYDE_TEMP_DIR.'/app/storage/framework/cache/packages.php';
    }

    public function getCachedConfigPath(): string
    {
        // Since we cache the app configuration within the Phar archive
        // we need to return the path to the cached config file here.
        return __DIR__.'/../app/storage/framework/cache/config.php';
    }

    public function getNamespace(): string
    {
        if (file_exists($this->basePath('composer.json'))) {
            return parent::getNamespace();
        }

        // Adds a fallback so that the application can still run without a composer.json file
        return 'App';
    }
}
