<?php

declare(strict_types=1);

namespace App\Foundation;

use Symfony\Component\Console\Input\InputInterface;
use Hyde\Foundation\ConsoleKernel as BaseConsoleKernel;

use function str_starts_with;

/**
 * Swaps in the executable's configuration loader, and makes an unknown command an error.
 *
 * @see \App\Foundation\LoadConfiguration
 */
class ConsoleKernel extends BaseConsoleKernel
{
    /**
     * Only fall back to the default command when no command was actually named.
     *
     * Laravel Zero proxies any unrecognised first argument to the default command,
     * which means a typo — or a command that only exists in a Composer project —
     * prints the command list and exits zero. The CLI reports that as the error
     * it is, so that a failed invocation can never be read as a success.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input
     */
    protected function ensureDefaultCommand($input): void
    {
        $argument = $input instanceof InputInterface ? $input->getFirstArgument() : null;

        if ($argument === null || str_starts_with($argument, '-')) {
            parent::ensureDefaultCommand($input);
        } else {
            // Boot without installing a fallback, so Symfony reports the unknown command.
            $this->bootstrap();
        }
    }

    /** @return list<class-string> */
    protected function bootstrappers(): array
    {
        $bootstrappers = parent::bootstrappers();

        foreach ($bootstrappers as $index => $bootstrapper) {
            if ($bootstrapper === \Hyde\Foundation\Internal\LoadConfiguration::class) {
                $bootstrappers[$index] = LoadConfiguration::class;
            }
        }

        return $bootstrappers;
    }
}
