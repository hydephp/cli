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
