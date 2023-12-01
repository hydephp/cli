<?php

declare(strict_types=1);

namespace App\Commands;

use LaravelZero\Framework\Commands\Command;

/**
 * @internal Wrapper for {@see \LaravelZero\Framework\Commands\BuildCommand}
 */
class BuildApplicationBinaryCommand extends Command
{
    protected $signature = 'standalone:build';
    protected $description = 'Build the standalone executable';

    public function handle(): int
    {
        //
    }
}
