<?php

declare(strict_types=1);

namespace App\Commands\Internal;

use LogicException;
use Illuminate\Support\Facades\File;
use LaravelZero\Framework\Commands\BuildCommand;
use LaravelZero\Framework\Commands\Command;
use Symfony\Component\Console\Output\ConsoleOutput;
use Throwable;

/**
 * @internal Wrapper for {@see \LaravelZero\Framework\Commands\BuildCommand}
 */
class BuildApplicationBinaryCommand extends Command
{
    protected $signature = 'standalone:build
                            {--build-version-suffix= : The optional build version suffix}';

    protected $description = 'Build the standalone executable';

    protected string $version;

    public function handle(): int
    {
        $this->title('Building standalone executable');
        $this->version = $this->getVersion();

        $this->setupBuildEnvironment();
        $this->clearCachedConfiguration();
        $this->cacheConfiguration();

        try {
            // Convert output implementation to type the build command expects
            $this->output = new ConsoleOutput();

            return $this->call(BuildCommand::class, [
                '--build-version' => $this->version,
            ]);
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            $this->resetBuildEnvironment();
        }
    }

    protected function getVersion(): string
    {
        return $this->getApplication()->getVersion() . ($this->option('build-version-suffix') ? sprintf(' (Build %s)', $this->option('build-version-suffix')) : '');
    }

    protected function setupBuildEnvironment(): void
    {
        copy(__DIR__.'/../../config.php', __DIR__.'/../../../config/app.php');
        copy(__DIR__.'/../../../box.json', __DIR__.'/../../../box.json.bak');
    }

    protected function resetBuildEnvironment(): void
    {
        unlink(__DIR__.'/../../../config/app.php');
        rename(__DIR__.'/../../../box.json.bak', __DIR__.'/../../../box.json');
    }

    protected function clearCachedConfiguration(bool $silent = false): void
    {
        $configPath = $this->laravel->getCachedConfigPath();

        if (File::exists($configPath)) {
            File::delete($configPath);
        }

        if (! $silent) {  
            $this->components->info('Configuration cache cleared successfully.');
        }
    }

    protected function cacheConfiguration(): void
    {
        // We cache the main app configuration file as Laravel does not like functioning without it,
        // but we want to be able to run the application without it, so we'll just cache it here,
        // so that we can load it from the Phar binary when running the console application.

        $config = $this->getFreshConfiguration();

        $configPath = $this->laravel->getCachedConfigPath();

        File::put($configPath, '<?php return '.var_export($config, true).';'.PHP_EOL);

        try {
            require $configPath;
        } catch (Throwable $exception) {
            File::delete($configPath);

            throw new LogicException('Your configuration files are not serializable.', 0, $exception);
        }

        $this->components->info('Configuration cached successfully.');
    }

    protected function getFreshConfiguration(): array
    {
        $app = include __DIR__.'/../../config.php';

        $app['version'] = $this->version;

        return ['app' => $app];
    }
}
