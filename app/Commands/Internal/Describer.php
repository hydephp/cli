<?php

declare(strict_types=1);

namespace App\Commands\Internal;

use Illuminate\Console\Command;
use NunoMaduro\LaravelConsoleSummary\Describer as BaseDescriber;

/**
 * @internal Custom Laravel summary command describer implementation.
 *
 * @depends on https://github.com/nunomaduro/laravel-console-summary/pull/20
 */
class Describer extends BaseDescriber
{
    protected static function sortCommandsInGroup(array &$commands): void
    {
        usort($commands, function (Command $a, Command $b): int {
            if ($a->getName() === 'new' && $b->getName() !== 'new') {
                return -1;
            } elseif ($a->getName() !== 'new' && $b->getName() === 'new') {
                return 1;
            } else {
                return strcmp($a->getName(), $b->getName());
            }
        });
    }
}
