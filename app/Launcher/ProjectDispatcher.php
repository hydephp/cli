<?php

declare(strict_types=1);

namespace App\Launcher;

use function ltrim;
use function fopen;
use function fread;
use function fclose;
use function getenv;
use function defined;
use function dirname;
use function realpath;
use function is_string;
use function strcasecmp;
use function array_keys;
use function proc_open;
use function proc_close;
use function array_merge;
use function str_replace;
use function array_values;
use function str_starts_with;

/**
 * Hands control of a Composer project over to the project's own entry point.
 *
 * The executable is a launcher here, and nothing more. It supplies the PHP
 * runtime and then starts a genuinely separate process running the project's
 * own `hyde` file against the project's own `vendor/autoload.php`. Nothing from
 * the embedded dependency graph is loaded into that process, and nothing from
 * the project's graph is loaded into this one.
 */
class ProjectDispatcher
{
    /** Records which entry point this process was dispatched into, to detect a dispatch loop. */
    public const DISPATCH_MARKER = 'HYDE_DISPATCHED_INTO';

    public function __construct(private readonly RuntimeManager $runtime = new RuntimeManager())
    {
        //
    }

    /**
     * Run the project's entry point and return its exit status.
     *
     * @param  list<string>  $arguments The arguments to forward, excluding the program name.
     *
     * @throws \App\Launcher\LauncherException If the project cannot be dispatched into.
     */
    public function dispatch(Project $project, array $arguments = []): int
    {
        $this->guardAgainstBrokenInstall($project);

        $entryPoint = (string) $project->entryPoint();

        $this->guardAgainstRecursion($entryPoint);

        $process = @proc_open($this->command($entryPoint, $arguments), $this->descriptors(), $pipes, $project->root, $this->environment($entryPoint));

        if ($process === false) {
            throw new LauncherException("Unable to start the project's Hyde executable at $entryPoint.");
        }

        return proc_close($process);
    }

    /**
     * The command that runs the project's entry point.
     *
     * The bundled runtime is named as the program to execute, rather than the entry point
     * being run as an executable and left to find an interpreter for itself. That is what
     * decides which PHP the project runs on: a shebang would resolve `php` through the
     * search path, which on a machine with a system PHP would silently be a different
     * interpreter than the one this executable carries, and on a machine without one
     * would fail. Augmenting the search path is for the project's own subprocesses,
     * and is never what starts the project itself.
     *
     * @param  list<string>  $arguments
     * @return list<string>
     */
    public function command(string $entryPoint, array $arguments = []): array
    {
        return array_merge([$this->runtime->path(), $entryPoint], array_values($arguments));
    }

    /**
     * Refuse to dispatch into an entry point that already dispatched into us.
     *
     * The launcher's self-dispatch check should make this impossible; this is the
     * second line of defence, so that a mistake surfaces as an error rather than
     * as an unbounded chain of processes.
     *
     * @throws \App\Launcher\LauncherException
     */
    protected function guardAgainstRecursion(string $entryPoint): void
    {
        $parent = getenv(self::DISPATCH_MARKER);

        if ($parent !== false && realpath($parent) === realpath($entryPoint)) {
            throw new LauncherException("Refusing to dispatch into $entryPoint again: it dispatched into this executable, which would loop forever.");
        }
    }

    /**
     * The environment for the dispatched process.
     *
     * The parent environment is passed through, plus a marker recording which entry point
     * we handed control to, and the bundled runtime at the front of the search path.
     *
     * @return array<string, string>
     */
    protected function environment(string $entryPoint): array
    {
        $environment = getenv();

        $environment[self::DISPATCH_MARKER] = $entryPoint;

        // Windows stores the search path as `Path`, so the existing key is found rather
        // than assumed: adding a second, differently cased one would leave the child
        // with two search paths and no say in which of them is used.
        $key = $this->searchPathKey($environment);

        $environment[$key] = $this->searchPath(is_string($environment[$key] ?? null) ? $environment[$key] : '');

        return $environment;
    }

    /**
     * Find the environment key holding the search path, whatever case the platform used.
     *
     * @param  array<string, string>  $environment
     */
    public function searchPathKey(array $environment): string
    {
        foreach (array_keys($environment) as $name) {
            if (strcasecmp((string) $name, 'PATH') === 0) {
                return (string) $name;
            }
        }

        return 'PATH';
    }

    /**
     * Put the directory holding the bundled PHP runtime at the front of the search path.
     *
     * This is the launcher doing its job rather than a fallback. A Hyde project shells out
     * to a bare `php` for its own subprocesses — the realtime compiler's server, most
     * obviously — and on a machine with no PHP installed there would be nothing for it
     * to find. The executable supplies the runtime, which is what makes `hyde serve`
     * work in a Composer project on a machine that has no PHP.
     */
    protected function searchPath(string $path): string
    {
        $runtime = dirname($this->runtime->path());

        return $path === '' ? $runtime : $runtime.PATH_SEPARATOR.$path;
    }

    /**
     * Refuse to continue when the project's dependency graph is not installed.
     *
     * This is the single most important guarantee in the launcher: a Hyde Composer
     * project with a broken install must never be built against the embedded
     * framework, because that would silently compile the site with a different
     * version of Hyde than the project declares.
     *
     * @throws \App\Launcher\LauncherException
     */
    public function guardAgainstBrokenInstall(Project $project): void
    {
        if (! $project->isComposer()) {
            throw new LauncherException('Only Composer projects can be dispatched.');
        }

        if (! $project->hasAutoloader()) {
            throw new LauncherException(<<<TEXT
            This is a Hyde Composer project, but its dependencies are not installed.

            Expected to find: {$project->autoloadPath()}

            Run `composer install` in {$project->root} and try again.

            Hyde will not build a Composer project using the framework bundled in the
            CLI, as that would use a different version of Hyde than your project
            declares. Nothing has been changed.
            TEXT);
        }

        if (! $project->hasEntryPoint()) {
            throw new LauncherException(<<<TEXT
            This is a Hyde Composer project, but it has no `hyde` executable.

            Expected to find: {$project->entryPoint()}

            Restore the file from the hyde/hyde skeleton, or run `composer install`
            in {$project->root} to have Composer put it back.
            TEXT);
        }

        if (! $this->isPhpScript((string) $project->entryPoint())) {
            throw new LauncherException(<<<TEXT
            The `hyde` file in this Composer project is not a PHP script.

            Found: {$project->entryPoint()}

            Hyde runs a Composer project through its own entry point, which has to be the
            PHP file the hyde/hyde skeleton provides. If you copied the Hyde executable
            into the project, remove it and run `composer install` to restore the file.
            TEXT);
        }
    }

    /**
     * Does the given file look like a PHP script rather than a native binary?
     *
     * The project's entry point is run by the bundled PHP runtime, so handing it a
     * compiled executable would fail in a way that says nothing useful. A script has
     * to *open* with a shebang or a PHP tag; matching anywhere in the file would
     * accept a binary that happens to contain the bytes somewhere inside it.
     */
    protected function isPhpScript(string $path): bool
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            return false;
        }

        $head = ltrim((string) fread($handle, 64), "\xEF\xBB\xBF \t\r\n");

        fclose($handle);

        return str_starts_with($head, '#!') || str_starts_with($head, '<?php') || str_starts_with($head, '<?=');
    }

    /**
     * Inherit the parent's standard streams so the child keeps its TTY.
     *
     * Passing the stream resources through gives the child the real file
     * descriptors, which keeps interactive prompts, colours, and piping
     * behaving exactly as they would if the project were run directly.
     *
     * @return array<int, mixed>
     */
    protected function descriptors(): array
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
}
