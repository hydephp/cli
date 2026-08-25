<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Internal\BundledProgramCommand;

/**
 * Runs the Composer bundled inside the executable, on the bundled PHP runtime.
 *
 * This is what closes the last gap in the "no PHP, no Composer" promise. The CLI
 * could already run a Composer project on a machine with neither; now it can
 * install one's dependencies there too:
 *
 *     hyde composer install
 *
 * It is answered before the project is detected, which is deliberate — a Composer
 * project with no `vendor/` is precisely the state the launcher refuses to run
 * anything else in, and this is the command that repairs it.
 */
class ComposerCommand extends BundledProgramCommand
{
    /** @var string */
    protected $signature = 'composer {args?* : The arguments to pass to Composer}';

    /** @var string */
    protected $description = 'Run the bundled Composer.';
}
