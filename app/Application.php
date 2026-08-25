<?php

declare(strict_types=1);

namespace App;

use Throwable;
use App\Launcher\Project;

use function file_exists;

/**
 * The console application that ships inside the Hyde executable.
 */
class Application extends \Hyde\Foundation\Application
{
    /**
     * The HydeCLI version.
     *
     * This is the version of the *executable*, and is independent of the HydePHP
     * framework version. The 0.11.x CLI line embeds and supports the unreleased HydePHP
     * v3 development line, but the two versions must never be made to track each other.
     */
    final public const APP_VERSION = '0.12.1';

    /**
     * Get the path to the cached packages.php file.
     *
     * A Portable project has no `app/storage` directory, and the executable that builds
     * it is read only, so everything the framework wants to write goes to the storage
     * path the bootstrapper pointed us at.
     */
    public function getCachedPackagesPath(): string
    {
        return $this->storagePath('framework/cache/packages.php');
    }

    public function getCachedConfigPath(): string
    {
        // Configuration is never cached to disk: the application configuration is loaded
        // straight out of the file bundled with the executable.
        return $this->storagePath('framework/cache/config.php');
    }

    /**
     * Get the application namespace.
     *
     * A Portable project has no `composer.json` of its own to read a namespace from, and
     * any manifest that happens to sit beside its content belongs to something else
     * entirely, so a namespace must never be inferred from it.
     */
    public function getNamespace(): string
    {
        if (! file_exists($this->basePath('composer.json'))) {
            return 'App';
        }

        try {
            return parent::getNamespace();
        } catch (Throwable) {
            // The manifest belongs to something that is not this project, and declares no
            // namespace we could be running under.
            return 'App';
        }
    }

    /** Get the default command for the application. */
    public function getName(): string
    {
        return 'list';
    }

    /** The project this application was booted against. */
    public function project(): Project
    {
        return $this->make(Project::class);
    }
}
