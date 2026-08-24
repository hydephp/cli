<?php

declare(strict_types=1);

namespace App\Foundation;

use ReflectionClass;
use App\Actions\GenerateBuildManifest;
use Symfony\Component\Finder\Finder;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Hyde\Foundation\Internal\LoadConfiguration as BaseLoadConfiguration;
use Illuminate\Foundation\Bootstrap\LoadConfiguration as IlluminateLoadConfiguration;

use function tap;
use function dirname;
use function basename;
use function in_array;
use function array_merge;

/**
 * Loads the application configuration that ships inside the executable.
 *
 * Hyde normally reads `app/config.php` relative to the project root, which works
 * for a Composer project because the project owns that file. A Portable project
 * has no `app` directory at all — the configuration belongs to the executable —
 * so we point the loader at the embedded file instead.
 *
 * The project's own `config` directory is still read by the parent implementation,
 * so a Portable project can override any Hyde configuration value it likes.
 */
class LoadConfiguration extends BaseLoadConfiguration
{
    /** The framework default, which assumes the project has an `app/storage` directory. */
    private const DEFAULT_MANIFEST_PATH = 'app/storage/framework/cache/build-manifest.json';

    /** Where a Portable project writes its build manifest instead. */
    public const PORTABLE_MANIFEST_PATH = '.hyde-cache/build-manifest.json';

    protected function loadConfigurationFiles(Application $app, Repository $repository): void
    {
        parent::loadConfigurationFiles($app, $repository);

        // A Portable project has no `app` directory to write the build manifest into.
        // A project that has configured its own location keeps it.
        if (in_array($repository->get('hyde.build_manifest_path'), [null, self::DEFAULT_MANIFEST_PATH], true)) {
            $repository->set('hyde.build_manifest_path', self::PORTABLE_MANIFEST_PATH);
        }

        // Swap in the build task that creates that directory before writing to it. The
        // framework keys tasks on their short class name, so this replaces the
        // framework's own manifest task rather than running alongside it.
        $repository->set('hyde.build_tasks', array_merge(
            (array) $repository->get('hyde.build_tasks', []), [GenerateBuildManifest::class]
        ));
    }

    /**
     * Get the framework's own default configuration files.
     *
     * The inherited implementation resolves each file with `realpath()`, which returns
     * false for a file inside a PHAR. Reading the pathname keeps the defaults
     * loadable when the application is running from the executable.
     *
     * @return array<string, array<string, mixed>>
     */
    protected function getBaseConfiguration(): array
    {
        $config = [];

        $directory = dirname((string) (new ReflectionClass(IlluminateLoadConfiguration::class))->getFileName(), 5).'/config';

        foreach (Finder::create()->files()->name('*.php')->in($directory) as $file) {
            $config[basename($file->getPathname(), '.php')] = require $file->getPathname();
        }

        return $config;
    }

    /** @return array<string, string> */
    protected function getConfigurationFiles(Application $app): array
    {
        return (array) tap(parent::getConfigurationFiles($app), /** @param array<string, string> $files */ function (array &$files): void {
            // Always use the application configuration bundled with the executable, overriding
            // the parent's assumption that it lives in the project being built.
            $files['app'] = dirname(__DIR__).'/config.php';
        });
    }
}
