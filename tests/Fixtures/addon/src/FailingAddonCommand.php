<?php

declare(strict_types=1);

namespace Hyde\CliTestAddon;

use Illuminate\Console\Command;

/**
 * A command that always fails, so that exit status propagation can be observed.
 *
 * Laravel Zero proxies an unrecognised command to its default one, which succeeds,
 * so a project needs a command that genuinely fails in order to prove that the
 * CLI hands the project's exit status back unchanged.
 */
class FailingAddonCommand extends Command
{
    /** @var string */
    protected $signature = 'test:fail {--code=17 : The exit status to fail with}';

    /** @var string */
    protected $description = 'Fixture command that exits with a failing status.';

    public function handle(): int
    {
        $this->line('Addon command failing on purpose');

        return (int) $this->option('code');
    }
}
