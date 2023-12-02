<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Support\Arr;
use Hyde\Console\Commands\ServeCommand;

/**
 * Extended serve command that can run from the standalone executable.
 */
class PharServeCommand extends ServeCommand
{
    protected function getExecutablePath(): string
    {
        $default = parent::getExecutablePath();

        if (file_exists($default)) {
            return $default;
        }

        return $this->createPharServer();
    }

    protected function createPharServer(): string
    {
        // Create a temporary (cached) file to store the extracted server.php file
        $path = HYDE_TEMP_DIR . '/bin/server.php';

        if (file_exists($path)) {
            return $path;
        }

        if (\Phar::running()) {
            $phar = \Phar::running();
            $phar = new \Phar($phar);
            $phar->extractTo(HYDE_TEMP_DIR, 'bin/server.php');
        } else {
            // We're running from the source code, so we need just copy the server.php file,
            // but transformed to inline the required constant definitions.
            file_put_contents($path, str_replace(
                "define('PHAR_PATH', \Phar::running(false));",
                "// For testing only:\n    define('PHAR_PATH', '".__DIR__."/../../builds/hyde');",
                file_get_contents(__DIR__ . '/../../bin/server.php')));
        }

        return $path;
    }

    protected function getEnvironmentVariables(): array
    {
        return Arr::whereNotNull(array_merge(parent::getEnvironmentVariables(), [
            'HYDE_PHAR_PATH' => \Phar::running(false) ?: 'false',
            'HYDE_BOOTSTRAP_PATH' => \Phar::running() ? 'phar://hyde.phar/app/anonymous-bootstrap.php' : realpath(__DIR__ . '/../anonymous-bootstrap.php'),
            'HYDE_WORKING_DIR' => HYDE_WORKING_DIR,
            'HYDE_TEMP_DIR' => HYDE_TEMP_DIR,
        ]));
    }
}
