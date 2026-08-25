<?php

declare(strict_types=1);

namespace App\Commands;

use App\Commands\Internal\BundledProgramCommand;

/**
 * Runs the PHP CLI bundled inside the executable.
 *
 * The runtime is here anyway — it is what serves a site and what runs a Composer
 * project — so anyone who installed Hyde to avoid installing PHP has a working
 * PHP available to them as well.
 *
 * It is Hyde's PHP, not the machine's: the extension set is the one
 * `build/runtime.json` pins for Hyde, and a script needing something outside it
 * will say so.
 */
class PhpCommand extends BundledProgramCommand
{
    /** @var string */
    protected $signature = 'php {args?* : The arguments to pass to PHP}';

    /** @var string */
    protected $description = 'Run the bundled PHP CLI.';
}
