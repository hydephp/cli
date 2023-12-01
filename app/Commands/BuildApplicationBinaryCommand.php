<?php

declare(strict_types=1);

namespace App\Commands;

use Throwable;
use LaravelZero\Framework\Commands\Command;
use LaravelZero\Framework\Commands\BuildCommand;
use Symfony\Component\Console\Output\ConsoleOutput;

/**
 * @internal Wrapper for {@see \LaravelZero\Framework\Commands\BuildCommand}
 */
class BuildApplicationBinaryCommand extends Command
{
    protected $signature = 'standalone:build';
    protected $description = 'Build the standalone executable';

    public function handle(): int
    {
        $this->setBuildEnvironment();

        try {
            // Convert output implementation to type the build command expects
            $this->output = new ConsoleOutput();

            return $this->call(BuildCommand::class);
        } catch (Throwable $exception) {
            $this->resetBuildEnvironment();

            throw $exception;
        }
    }

    protected function setBuildEnvironment(): void
    {
        copy(__DIR__ . '/../config.php', __DIR__ . '/../../config/app.php');
    }

    protected function resetBuildEnvironment(): void
    {
        unlink(__DIR__ . '/../../config/app.php');
    }
}
