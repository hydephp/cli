<?php

declare(strict_types=1);

namespace App\Commands;

use Hyde\Console\Commands\ServeCommand;
use Illuminate\Support\Arr;

/**
 * Extended serve command that can run from the standalone executable.
 */
class PharServeCommand extends ServeCommand
{
    protected function getExecutablePath(): string
    {
        if (! $this->isPharRunning()) {
            // We're running from the source code, so we need to use the server.php file
            return __DIR__.'/../../bin/test-server.php';
        }

        $default = parent::getExecutablePath();

        if (file_exists($default)) {
            return $default;
        }

        return $this->createPharServer();
    }

    protected function createPharServer(): string
    {
        // Create a temporary (cached) file to store the extracted server.php file
        $path = HYDE_TEMP_DIR.'/bin/server.php';

        if (file_exists($path)) {
            return $path;
        }

        $this->extractServerFromPhar();

        return $path;
    }

    protected function getEnvironmentVariables(): array
    {
        return Arr::whereNotNull(array_merge(parent::getEnvironmentVariables(), [
            'HYDE_PHAR_PATH' => $this->getPharPath() ?: 'false',
            'HYDE_BOOTSTRAP_PATH' => $this->isPharRunning() ? 'phar://hyde.phar/app/anonymous-bootstrap.php' : realpath(__DIR__.'/../anonymous-bootstrap.php'),
            'HYDE_WORKING_DIR' => HYDE_WORKING_DIR,
            'HYDE_TEMP_DIR' => HYDE_TEMP_DIR,
        ]));
    }

    protected function getPharUrl(): string
    {
        return \Phar::running();
    }

    protected function getPharPath(): string
    {
        return \Phar::running(false);
    }

    protected function isPharRunning(): bool
    {
        return $this->getPharUrl() !== '';
    }

    protected function extractServerFromPhar(): void
    {
        $phar = $this->getPharUrl();
        $phar = new \Phar($phar);
        $phar->extractTo(HYDE_TEMP_DIR, 'bin/server.php');
    }
}
