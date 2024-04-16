<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Console\Command;

class SelfUpdateCommand extends Command
{
    /** @var string */
    protected $signature = 'self-update';

    /** @var string */
    protected $description = 'Update the standalone application to the latest version.';

    public function handle(): void
    {
        $this->output->title('Checking for a new version...');

        //
    }
}
