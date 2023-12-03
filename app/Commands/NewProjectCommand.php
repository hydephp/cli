<?php

declare(strict_types=1);

namespace App\Commands;

use Illuminate\Console\Command;

/**
 * Creates a new Hyde project.
 */
class NewProjectCommand extends Command
{
    /** @var string */
    protected $signature = 'new {name : The name of the project}';

    /** @var string */
    protected $description = 'Create a new Hyde project.';
}
