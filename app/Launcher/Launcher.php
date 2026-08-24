<?php

declare(strict_types=1);

namespace App\Launcher;

use Phar;
use Throwable;

use function md5;
use function is_file;
use function realpath;
use function get_included_files;
use function trim;
use function define;
use function defined;
use function fwrite;
use function getcwd;
use function getenv;
use function sprintf;
use function is_string;
use function in_array;
use function array_slice;
use function str_replace;
use function str_starts_with;
use function sys_get_temp_dir;

/**
 * The first thing that runs when the `hyde` executable starts.
 *
 * Everything here happens before the embedded autoloader is registered, which is
 * what lets the CLI hand a Composer project over to its own dependency graph
 * without the embedded framework ever having been loaded into the process.
 *
 * @see docs/ARCHITECTURE.md
 */
final class Launcher
{
    /**
     * Commands that belong to the CLI itself rather than to a project.
     *
     * These are answered by the executable even inside a Composer project, since
     * they are about the CLI (`self-update`), about the environment (`info`), or
     * about creating a project that does not exist yet (`new`). Everything else
     * in a Composer project is dispatched into that project.
     *
     * @var list<string>
     */
    public const LAUNCHER_COMMANDS = ['info', 'new', 'self-update'];

    private static ?Project $project = null;

    public function __construct(
        private readonly ProjectDetector $detector = new ProjectDetector(),
        private readonly ProjectDispatcher $dispatcher = new ProjectDispatcher(),
    ) {
        //
    }

    /**
     * Detect the project, dispatch it when it owns its own dependency graph,
     * and otherwise prepare the environment for the embedded application.
     *
     * @param  list<string>  $argv The raw `$argv`, including the program name.
     * @return int|null The exit status when the call was handled here, or null to continue booting.
     */
    public function run(array $argv): ?int
    {
        $project = $this->detect();

        // The CLI's own source checkout is itself a Hyde Composer project. When the file
        // we are running *is* the project's entry point, dispatching would relaunch
        // this very script forever, so we boot the application we already are.
        $isSelf = $this->isSelfDispatch($project);

        if ($project->isComposer() && ! $isSelf && ! $this->isLauncherCommand($argv)) {
            return $this->dispatcher->dispatch($project, array_slice($argv, 1));
        }

        $this->defineEnvironment($project, isolated: $project->isComposer() && ! $isSelf);

        return null;
    }

    /**
     * Is the project's entry point the very PHP script that is running right now?
     *
     * This is true only when the CLI is run out of its own source checkout, where the
     * repository is itself a Hyde Composer project whose `hyde` file is the script
     * being executed. Dispatching there would relaunch this script forever.
     *
     * A packaged executable is deliberately excluded. It can be copied to a project's
     * `./hyde`, and treating that as "being the project" would boot the embedded
     * framework against a Composer project — the one thing that must never happen.
     */
    public function isSelfDispatch(Project $project): bool
    {
        if (Phar::running() !== '') {
            return false;
        }

        $entryPoint = $project->entryPoint();

        if ($entryPoint === null || ! is_file($entryPoint)) {
            return false;
        }

        $self = $this->selfPath();

        return $self !== null && realpath($entryPoint) === realpath($self);
    }

    /**
     * The absolute path of the script or executable currently running.
     *
     * The script name can reach us relative to the working directory, so it is always
     * resolved: a relative path would compare unequal to the project's entry point
     * even when they are the same file.
     */
    public function selfPath(): ?string
    {
        $running = Phar::running(false);

        if ($running !== '') {
            return $running;
        }

        $script = $_SERVER['SCRIPT_FILENAME'] ?? null;

        if (is_string($script) && $script !== '' && is_file($script)) {
            return realpath($script) ?: $script;
        }

        $included = get_included_files();

        return isset($included[0]) ? (realpath($included[0]) ?: $included[0]) : null;
    }

    public function detect(): Project
    {
        return self::$project ??= $this->detector->detect($this->workingDirectory());
    }

    /** The project the CLI was invoked against. Available to the application once the launcher has run. */
    public static function project(): Project
    {
        return self::$project ?? throw new LauncherException('The launcher has not detected a project yet.');
    }

    /** @internal Test hook to reset the memoized detection between test cases. */
    public static function swap(?Project $project): void
    {
        self::$project = $project;
    }

    /**
     * Which command was requested, if any.
     *
     * @param  list<string>  $argv
     */
    public function commandName(array $argv): ?string
    {
        foreach (array_slice($argv, 1) as $argument) {
            if (! str_starts_with($argument, '-')) {
                return $argument;
            }
        }

        return null;
    }

    /** @param list<string> $argv */
    public function isLauncherCommand(array $argv): bool
    {
        return in_array($this->commandName($argv), self::LAUNCHER_COMMANDS, true);
    }

    /**
     * Resolve, and define, the paths the embedded application boots against.
     *
     * @return array{type: string, root: string, temp: string, working: string}
     *
     * In Portable mode the project root is the application base path: the working
     * directory *is* the site. When a launcher-owned command runs inside somebody
     * else's Composer project the embedded application is pointed at a scratch
     * directory instead, so that it can never read that project's configuration
     * files or discover that project's packages.
     */
    public function defineEnvironment(Project $project, bool $isolated = false): array
    {
        $temp = $this->temporaryDirectory($project);
        $working = $isolated ? $temp.'/isolated' : $project->root;

        // The constants are how the serve subprocess and the framework's Phar helpers read
        // these paths. They can only be defined once, so the resolved values are also
        // returned: within one process the application may be booted more than once.
        $this->define('HYDE_PROJECT_TYPE', $project->type->value);
        $this->define('HYDE_PROJECT_ROOT', $project->root);
        $this->define('HYDE_TEMP_DIR', $temp);
        $this->define('HYDE_WORKING_DIR', $working);

        return ['type' => $project->type->value, 'root' => $project->root, 'temp' => $temp, 'working' => $working];
    }

    private function temporaryDirectory(Project $project): string
    {
        $override = getenv('HYDE_TEMP_DIR');

        if (is_string($override) && $override !== '') {
            return Project::normalize($override);
        }

        return sprintf('%s/hyde/%s', Project::normalize(sys_get_temp_dir()), md5($project->root.'-'.$project->type->value));
    }

    public function workingDirectory(): string
    {
        $override = getenv('HYDE_WORKING_DIR');

        if (is_string($override) && $override !== '') {
            return Project::normalize($override);
        }

        return Project::normalize(getcwd() ?: '.');
    }

    private function define(string $name, string $value): void
    {
        if (! defined($name)) {
            define($name, $value);
        }
    }

    /**
     * Report a fatal launcher error and return the exit status to terminate with.
     *
     * Launcher errors are written to standard error so that they cannot be mistaken
     * for command output, and they always produce a non-zero status.
     */
    public function fail(Throwable $exception): int
    {
        $message = trim($exception->getMessage());

        fwrite(STDERR, "\n  Hyde could not start:\n\n  ".str_replace("\n", "\n  ", $message)."\n\n");

        $status = $exception instanceof LauncherException ? $exception->status : 1;

        return $status === 0 ? 1 : $status;
    }
}
