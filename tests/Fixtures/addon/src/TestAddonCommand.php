<?php

declare(strict_types=1);

namespace Hyde\CliTestAddon;

use Illuminate\Console\Command;

use function dirname;

/**
 * A command that can only exist when the project's own dependency graph is loaded.
 *
 * It prints where its own class file was loaded from, so a test can prove the
 * command actually executed inside the project rather than merely appearing
 * in a listing produced by the CLI's embedded application.
 */
class TestAddonCommand extends Command
{
    /** @var string */
    protected $signature = 'test:addon';

    /** @var string */
    protected $description = 'Fixture command provided by a project-only Composer addon.';

    public function handle(): int
    {
        $this->line('Addon command executed');
        $this->line('Loaded from: '.dirname(__DIR__));
        $this->line('Project root: '.$this->laravel->basePath());

        return Command::SUCCESS;
    }
}
