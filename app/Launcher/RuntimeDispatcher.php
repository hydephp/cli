<?php

declare(strict_types=1);

namespace App\Launcher;

use function fwrite;
use function sprintf;
use function in_array;
use function array_merge;
use function array_values;
use function str_starts_with;

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
    /**
     * What Composer calls its own updater, refused here.
     *
     * The bundled Composer is versioned with the executable, not with itself.
     *
     * @var list<string>
     */
    public const SELF_UPDATE = ['self-update', 'selfupdate'];

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
        $refused = $this->refuseSelfUpdate($arguments);

        if ($refused !== null) {
            return $refused;
        }

        $php = $this->runtime->path();

        return $this->start(array_merge([$php, $this->runtime->composerPath()], array_values($arguments)), $php);
    }

    /**
     * Refuse to let the bundled Composer replace itself.
     *
     * It is extracted from the executable and verified against the checksum recorded at
     * build time on every run, so an update to the cached copy would be repaired away by
     * the very next command — silently, since repairing a copy that fails verification
     * is exactly what the extraction does. Saying so beats appearing to work.
     *
     * The check reads the first argument that is not an option, and stops looking at the
     * first one that is not the updater: an option that took *its* value as a separate
     * token would otherwise be read as a command name. A guard is allowed to miss;
     * it is not allowed to block something the user did not ask for.
     *
     * This is not raised as a `LauncherException`: nothing failed to start, and that is
     * what the launcher reports those as.
     *
     * @param  list<string>  $arguments
     * @return int|null The exit status to stop with, or null to carry on.
     */
    protected function refuseSelfUpdate(array $arguments): ?int
    {
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '-')) {
                continue;
            }

            if (! in_array($argument, self::SELF_UPDATE, true)) {
                return null;
            }

            fwrite(STDERR, sprintf(<<<'TEXT'

              `composer %s` is not available here.

              The Composer inside this executable is versioned with the HydeCLI, and is
              verified against the checksum recorded when the executable was built. An
              update would be replaced again by the next command that needs it.

              Run `hyde self-update` for an executable carrying a newer Composer, or
              install Composer yourself and run that one.


            TEXT, $argument));

            return 1;
        }

        return null;
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
