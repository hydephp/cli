<?php

declare(strict_types=1);

namespace App\Launcher;

use function sprintf;
use function array_merge;
use function array_values;

/**
 * Runs the programs the executable carries inside it.
 *
 * The `hyde` executable already ships a complete PHP CLI so that it can serve a site
 * and run a Composer project's own entry point. These commands hand that runtime,
 * and the Composer it is shipped with, straight to the user: somebody who
 * installed Hyde precisely so they would not have to install PHP has a
 * usable PHP and Composer anyway.
 *
 * They are answered here, in the launcher, rather than by a console command, for two
 * reasons. Arguments have to reach the child exactly as they were typed, and a
 * console application would eat `-v`, `--version` and `--help` before the
 * command ever saw them. And `hyde composer install` has to work in a
 * Composer project whose `vendor/` is missing — the very state the
 * launcher otherwise refuses to run anything in — which means it
 * must be answered before dispatch is even considered.
 *
 * @see docs/ARCHITECTURE.md
 */
class RuntimeDispatcher
{
    public function __construct(private readonly RuntimeManager $runtime = new RuntimeManager())
    {
        //
    }

    /**
     * Run one of the bundled programs and return its exit status.
     *
     * @param  list<string>  $arguments The arguments to forward, exactly as they were typed.
     *
     * @throws \App\Launcher\LauncherException If the program cannot be provided or started.
     */
    public function run(string $command, array $arguments = []): int
    {
        return match ($command) {
            'php' => $this->php($arguments),
            'composer' => $this->composer($arguments),
            default => throw new LauncherException("The executable bundles no `$command` program."),
        };
    }

    /**
     * Run the bundled PHP CLI.
     *
     * Nothing is added to what the user typed, and nothing is interpreted: the runtime
     * behaves exactly as the same PHP binary would if it were installed on the host.
     *
     * @param  list<string>  $arguments
     *
     * @throws \App\Launcher\LauncherException
     */
    public function php(array $arguments = []): int
    {
        $php = $this->runtime->path();

        return $this->start(array_merge([$php], array_values($arguments)), $php);
    }

    /**
     * Run the bundled Composer, using the bundled PHP runtime.
     *
     * Composer is a PHAR, so the runtime is named as the program and Composer as its
     * first argument. That is also what decides which PHP the install runs against:
     * the one this executable ships, and never whatever a shebang would find.
     *
     * @param  list<string>  $arguments
     *
     * @throws \App\Launcher\LauncherException
     */
    public function composer(array $arguments = []): int
    {
        $php = $this->runtime->path();

        return $this->start(array_merge([$php, $this->runtime->composerPath()], array_values($arguments)), $php);
    }

    /**
     * Start a bundled program, with the runtime on its search path.
     *
     * The working directory is inherited rather than set to the project root, because
     * these commands are not about the project: `hyde php script.php` has to resolve
     * that path from wherever the user is standing.
     *
     * @param  list<string>  $command
     *
     * @throws \App\Launcher\LauncherException
     */
    private function start(array $command, string $runtime): int
    {
        return Subprocess::run($command, null, Subprocess::environmentWith($runtime), sprintf('Unable to start the bundled PHP runtime at %s.', $runtime));
    }
}
