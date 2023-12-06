<?php

declare(strict_types=1);

namespace App\Commands\Internal;

use Throwable;
use LaravelZero\Framework\Commands\Command;
use LaravelZero\Framework\Commands\BuildCommand;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * @internal Wrapper for {@see \LaravelZero\Framework\Commands\BuildCommand}
 */
class BuildApplicationBinaryCommand extends Command
{
    protected $signature = 'standalone:build
                            {--build-version-suffix= : The optional build version suffix}';

    protected $description = 'Build the standalone executable';

    public function handle(): int
    {
        $this->setupBuildEnvironment();

        try {
            // Convert output implementation to type the build command expects
            $this->output = new ConsoleOutput();

            return $this->call(BuildCommand::class, [
                '--build-version' => ($this->getApplication()->getVersion() . ($this->option('build-version-suffix') ? sprintf(' (Build %s)', $this->option('build-version-suffix')) : '')),
            ]);
        } catch (Throwable $exception) {
            throw $exception;
        } finally {
            $this->resetBuildEnvironment();
        }
    }

    protected function setupBuildEnvironment(): void
    {
        copy(__DIR__ . '/../../config.php', __DIR__ . '/../../../config/app.php');
        copy(__DIR__ . '/../../../box.json', __DIR__ . '/../../../box.json.bak');
    }

    protected function resetBuildEnvironment(): void
    {
        unlink(__DIR__ . '/../../../config/app.php');
        rename(__DIR__ . '/../../../box.json.bak', __DIR__ . '/../../../box.json');
    }
}
