<?php

declare(strict_types=1);

namespace App\Commands;

use Hyde\Console\Commands\ServeCommand as BaseServeCommand;
use Illuminate\Support\Facades\File;
use Phar;

/**
 * Extended serve command that can run from the standalone executable.
 */
class ServeCommand extends BaseServeCommand
{
    protected function getExecutablePath(): string
    {
        $default = parent::getExecutablePath();

        if (File::exists($default)) {
            return $default;
        }

        return $this->proxyPharServer();
    }

    /** Creates a temporary (cached) file to store the server executable */
    protected function proxyPharServer(): string
    {
        $path = HYDE_TEMP_DIR.'/bin/server.php';

        if (File::exists($path)) {
            return $path;
        }

        $this->createServer($path);

        return $path;
    }

    protected function getEnvironmentVariables(): array
    {
        return array_merge(parent::getEnvironmentVariables(), [
            'HYDE_PHAR_PATH' => $this->getPharPath() ?: 'false',
            'HYDE_BOOTSTRAP_PATH' => $this->getBootstrapPath(),
            'HYDE_WORKING_DIR' => HYDE_WORKING_DIR,
            'HYDE_TEMP_DIR' => HYDE_TEMP_DIR,
        ]);
    }

    protected function getBootstrapPath(): string
    {
        return $this->isPharRunning() ? 'phar://hyde.phar/app/bootstrap.php' : realpath(__DIR__.'/../bootstrap.php');
    }

    protected function getPharPath(): string
    {
        return Phar::running(false) ?: realpath(__DIR__.'/../../builds/hyde') ?: 'false';
    }

    protected function isPharRunning(): bool
    {
        return Phar::running() !== '';
    }

    protected function createServer(string $path): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $this->getServerStub($this->getPharPath()));
    }

    protected function getServerStub(string $phar): string
    {
        return <<<PHP
        <?php
        // Proxies the realtime compiler server from the Phar archive

        Phar::loadPhar('$phar', 'hyde.phar');

        putenv('HYDE_AUTOLOAD_PATH=phar://hyde.phar/vendor/autoload.php');

        return require 'phar://hyde.phar/vendor/hyde/realtime-compiler/bin/server.php';
        PHP;
    }
}
