<?php

declare(strict_types=1);

namespace App\Commands\Internal;

use App\Launcher\Launcher;
use App\Launcher\RuntimeManager;
use App\Launcher\RuntimeDispatcher;
use Illuminate\Console\Command;

/**
 * @internal A command that hands control to one of the programs bundled in the executable.
 *
 * The work is done by {@see \App\Launcher\RuntimeDispatcher}, which the launcher calls
 * before the application is booted at all: that is the only way arguments can reach
 * the program exactly as they were typed, since a console application would claim
 * `-v`, `--version` and `--help` for itself long before a command ran.
 *
 * These classes exist so the CLI can describe what it bundles — `hyde list` and
 * `hyde help php` need something to describe — and so the commands still work
 * if the application is booted directly, without the launcher in front of it.
 * They read the raw arguments rather than parsed ones for the same reason.
 */
abstract class BundledProgramCommand extends Command
{
    public function __construct()
    {
        parent::__construct();

        // The options being forwarded are the program's, and are none of our business.
        $this->ignoreValidationErrors();
    }

    public function handle(): int
    {
        return $this->dispatcher()->run((string) $this->getName(), (new Launcher())->argumentsFor($_SERVER['argv'] ?? []));
    }

    protected function dispatcher(): RuntimeDispatcher
    {
        return new RuntimeDispatcher($this->laravel->make(RuntimeManager::class));
    }
}
