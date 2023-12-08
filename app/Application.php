<?php

declare(strict_types=1);

namespace App;

class Application extends \Hyde\Foundation\Application
{
    public function getCachedPackagesPath(): string
    {
        // Since we have a custom path for the cache directory, we need to return it here.
        return HYDE_TEMP_DIR . '/app/storage/framework/cache/packages.php';
    }

    public function getNamespace()
    {
        if (file_exists($this->basePath('composer.json'))) {
            return parent::getNamespace();
        }

        // Adds a fallback so that the application can still run without a composer.json file
        return 'App';
    }
}
