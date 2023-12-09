<?php

declare(strict_types=1);

namespace App\Commands\Internal;

use NunoMaduro\LaravelConsoleSummary\Describer as BaseDescriber;

class Describer extends BaseDescriber
{
    protected static function sortCommandsInGroup(array &$commands): void
    {
        usort($commands, function ($a, $b) {
            if  ($a->getName() === 'new') {
                return -1;
            }

            return strcmp($a->getName(), $b->getName());
        });
    }
}
