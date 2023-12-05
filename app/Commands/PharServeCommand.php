<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Support\Facades\File;
use Hyde\Console\Commands\ServeCommand;
use Illuminate\Support\Arr;

/**
 * Extended serve command that can run from the standalone executable.
 */
class PharServeCommand extends ServeCommand
{
    protected function getExecutablePath(): string
    {
        $default = parent::getExecutablePath();

        if (File::exists($default)) {
            return $default;
        }

        return $this->createPharServer();
    }

    protected function createPharServer(): string
    {
        // Create a temporary (cached) file to store the extracted server.php file
        $path = HYDE_TEMP_DIR.'/bin/server.php';

        if (File::exists($path)) {
            return $path;
        }

        $this->extractServerFromPhar();

        return $path;
    }

    protected function getEnvironmentVariables(): array
    {
        return Arr::whereNotNull(array_merge(parent::getEnvironmentVariables(), [
            'HYDE_PHAR_PATH' => $this->getPharPath() ?: 'false',
            'HYDE_BOOTSTRAP_PATH' => $this->isPharRunning() ? 'phar://hyde.phar/app/bootstrap.php' : realpath(__DIR__.'/../bootstrap.php'),
            'HYDE_WORKING_DIR' => HYDE_WORKING_DIR,
            'HYDE_TEMP_DIR' => HYDE_TEMP_DIR,
        ]));
    }

    /** @internal */
    protected function getPharUrl(): string
    {
        return \Phar::running();
    }

    /** @internal */
    protected function getPharPath(): string
    {
        return \Phar::running(false);
    }

    /** @internal */
    protected function isPharRunning(): bool
    {
        return $this->getPharUrl() !== '';
    }

    /** @codeCoverageIgnore as tests are run from source code */
    protected function extractServerFromPhar(): void
    {
        $phar = new \Phar($this->getPharUrl());
        $phar->extractTo(HYDE_TEMP_DIR, 'bin/server.php');
    }
}
