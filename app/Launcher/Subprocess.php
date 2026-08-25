<?php

declare(strict_types=1);

namespace App\Launcher;

use function getenv;
use function dirname;
use function defined;
use function is_string;
use function proc_open;
use function array_keys;
use function proc_close;
use function strcasecmp;
use function array_values;

/**
 * Starts a child process that inherits this one's terminal.
 *
 * The launcher hands work to other programs in two places: a Composer project is run
 * through its own entry point, and the bundled runtime commands are run through the
 * embedded PHP binary. Both need the same three things — the child keeping the real
 * standard streams, the bundled runtime being findable on the search path, and the
 * child's exit status being returned rather than swallowed — so they live here
 * rather than being written twice.
 *
 * Like everything else in the launcher, this depends on nothing but the PHP standard
 * library: it runs before the embedded autoloader is registered.
 */
final class Subprocess
{
    /**
     * Run a program to completion and return its exit status.
     *
     * The command is an array, which bypasses the shell on both platforms: there is no
     * quoting to get wrong, and no difference between how `cmd.exe` and `sh` would
     * read the line.
     *
     * @param  list<string>  $command The program, then its arguments, each unquoted.
     * @param  array<string, string>|null  $environment The child's environment, or null to inherit ours.
     *
     * @throws \App\Launcher\LauncherException If the process cannot be started.
     */
    public static function run(array $command, ?string $workingDirectory = null, ?array $environment = null, ?string $failure = null): int
    {
        $process = @proc_open(array_values($command), self::descriptors(), $pipes, $workingDirectory, $environment);

        if ($process === false) {
            throw new LauncherException($failure ?? 'Unable to start '.($command[0] ?? 'the requested program').'.');
        }

        return proc_close($process);
    }

    /**
     * Inherit the parent's standard streams so the child keeps its TTY.
     *
     * Passing the stream resources through gives the child the real file
     * descriptors, which keeps interactive prompts, colours, and piping
     * behaving exactly as they would if the program were run directly.
     *
     * @return array<int, mixed>
     */
    public static function descriptors(): array
    {
        if (defined('STDIN') && defined('STDOUT') && defined('STDERR')) {
            return [STDIN, STDOUT, STDERR];
        }

        return [
            ['file', 'php://stdin', 'r'],
            ['file', 'php://stdout', 'w'],
            ['file', 'php://stderr', 'w'],
        ];
    }

    /**
     * Our own environment, with the directory holding the bundled PHP runtime at the
     * front of the search path.
     *
     * This is the launcher doing its job rather than a fallback. The programs it starts
     * shell out to a bare `php` for their own subprocesses — the realtime compiler's
     * server and Composer's install scripts, most obviously — and on a machine with no
     * PHP installed there would be nothing for them to find.
     *
     * @return array<string, string>
     */
    public static function environmentWith(string $runtime): array
    {
        $environment = getenv();

        $key = self::searchPathKey($environment);
        $directory = dirname($runtime);
        $path = is_string($environment[$key] ?? null) ? $environment[$key] : '';

        $environment[$key] = $path === '' ? $directory : $directory.PATH_SEPARATOR.$path;

        return $environment;
    }

    /**
     * Find the environment key holding the search path, whatever case the platform used.
     *
     * Windows stores it as `Path`, so the existing key is found rather than assumed:
     * adding a second, differently cased one would leave the child with two search
     * paths and no say in which of them is used.
     *
     * @param  array<string, string>  $environment
     */
    public static function searchPathKey(array $environment): string
    {
        foreach (array_keys($environment) as $name) {
            if (strcasecmp((string) $name, 'PATH') === 0) {
                return (string) $name;
            }
        }

        return 'PATH';
    }
}
