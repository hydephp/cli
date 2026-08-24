<?php

declare(strict_types=1);

namespace App\Commands;

use Closure;
use App\Application;
use App\Launcher\Project;
use App\Support\ComposerBinary;
use App\Support\PortableProjectBuilder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Hyde\Console\ConsoleServiceProvider;

use function app;
use function trim;
use function explode;
use function getenv;
use function is_string;
use function json_encode;
use function substr;
use function is_dir;
use function sprintf;
use function strrpos;
use function str_starts_with;
use function Laravel\Prompts\text;
use function Laravel\Prompts\select;

/**
 * Creates a new Hyde project, of either of the two supported kinds.
 */
class NewProjectCommand extends Command
{
    /** @var string */
    protected $signature = 'new
                            {name? : The name of the project}
                            {--portable : Create a portable site that needs no PHP or Composer}
                            {--composer : Create a full Composer project with addon support}';

    /** @var string */
    protected $description = 'Create a new Hyde project.';

    /** The version constraint used when creating a project from the unreleased v3 development line. */
    protected const DEVELOPMENT_VERSION = '3.0.0-dev';

    protected const COMPOSER_MISSING = <<<'TEXT'
    Creating a Composer project requires Composer.

    Use `hyde new NAME --portable` for a dependency-free Hyde site,
    or install Composer and retry.
    TEXT;

    public function handle(): int
    {
        $this->output->write($this->withAnsi() ? $this->getLogo() : 'Welcome to HydePHP!');

        $name = $this->argument('name') ?? text('What is the name of your project?', required: 'Please provide a name for your project.');

        return $this->selectProjectType() === 'composer'
            ? $this->createComposerProject($name)
            : $this->createPortableProject($name);
    }

    /** @return 'portable'|'composer' */
    protected function selectProjectType(): string
    {
        if ($this->option('composer')) {
            return 'composer';
        }

        if ($this->option('portable') || ! $this->input->isInteractive()) {
            // Portable is the default, and what a non-interactive run gets.
            return 'portable';
        }

        $this->newLine();
        $this->line('  <options=bold>What kind of Hyde site would you like to create?</>');
        $this->newLine();
        $this->line('  <options=bold>Portable site</> <fg=gray>(recommended)</>');
        $this->line('  <fg=gray>Content and configuration only. No PHP or Composer required.</>');
        $this->line('  <fg=gray>Fastest builds and easiest deployment.</>');
        $this->newLine();
        $this->line('  <options=bold>Composer project</>');
        $this->line('  <fg=gray>Full Hyde project with Composer dependency and addon support.</>');
        $this->newLine();

        return select(
            label: 'Which would you like to create?',
            options: ['portable' => 'Portable site', 'composer' => 'Composer project'],
            default: 'portable',
        );
    }

    protected function createPortableProject(string $name): int
    {
        $path = $this->resolvePath($name);

        if (is_dir($path) && ! File::isEmptyDirectory($path)) {
            $this->error("The directory $path already exists and is not empty.");

            return Command::FAILURE;
        }

        (new PortableProjectBuilder($path, $name))->create();

        $this->newLine();
        $this->info("Created a portable Hyde site in $path");
        $this->line('  <fg=gray>No PHP or Composer needed. Run</> <comment>cd '.$name.' && hyde build</comment> <fg=gray>to build it.</>');

        return Command::SUCCESS;
    }

    protected function createComposerProject(string $name): int
    {
        // The check happens before anything is written, so a machine without Composer
        // is never left with a half-created project directory.
        $composer = ComposerBinary::locate();

        if ($composer === null) {
            $this->newLine();
            $this->output->writeln('<error>'.self::COMPOSER_MISSING.'</error>');

            return Command::FAILURE;
        }

        $path = $this->resolvePath($name);
        $existed = is_dir($path);

        $result = Process::forever()->path($this->workingDirectory())->run(
            $this->createProjectCommand($composer, $name),
            $this->bufferedOutput()
        );

        if ($result->failed()) {
            if (! $existed && is_dir($path)) {
                // Composer created the directory before failing; do not leave a broken project behind.
                File::deleteDirectory($path);
            }

            $this->newLine();
            $this->error(sprintf('Creating the project failed: Composer exited with code %d.', $result->exitCode()));

            return $result->exitCode() ?: Command::FAILURE;
        }

        $this->newLine();
        $this->info("Created a Hyde Composer project in $path");

        return Command::SUCCESS;
    }

    /**
     * Build the Composer invocation that creates the project.
     *
     * The project major is pinned to the executable's own major, so a HydeCLI 3.x binary
     * creates a Hyde 3.x project and nothing else. Asking Composer for an unconstrained
     * `hyde/hyde` would mean this executable creating a Hyde 4 project on the day Hyde 4
     * becomes the latest stable release — a project it was never built to run, using a
     * framework whose commands and configuration it knows nothing about.
     *
     * Until v3 is tagged there is no published release in that range to create from, so
     * the build and the test suite point the command at the v3 development line through
     * `HYDE_PROJECT_SOURCE`. That variable is a development mechanism, is never set for a
     * released executable, and is the only thing that can move this command off Packagist.
     *
     * @return list<string>
     */
    protected function createProjectCommand(string $composer, string $name): array
    {
        $source = static::developmentSource();

        // Composer takes the package, then the directory, then the version constraint.
        $command = [$composer, 'create-project', 'hyde/hyde', $name, $this->projectConstraint($source)];

        $command[] = '--prefer-dist';
        $command[] = $this->withAnsi() ? '--ansi' : '--no-ansi';

        if ($source !== null) {
            $command[] = '--repository='.json_encode(['type' => 'path', 'url' => $source, 'options' => ['symlink' => false]], JSON_UNESCAPED_SLASHES);
            $command[] = '--stability=dev';

            $this->line("  <fg=yellow>Creating the project from the HydePHP v3 development line at $source</>");
        }

        return $command;
    }

    /**
     * The version of Hyde to create a project from.
     *
     * Derived from the executable's own version rather than written out, so that the
     * relationship holds by construction when the CLI's major is bumped.
     */
    protected function projectConstraint(?string $source): string
    {
        if ($source !== null) {
            return static::DEVELOPMENT_VERSION;
        }

        return sprintf('^%s.0', explode('.', Application::APP_VERSION)[0]);
    }

    /** The local checkout to create a project from while v3 is unreleased, if one was configured. */
    protected static function developmentSource(): ?string
    {
        $source = getenv('HYDE_PROJECT_SOURCE');

        return is_string($source) && $source !== '' ? $source : null;
    }

    /** Resolve the target path against the directory the CLI was invoked from. */
    protected function resolvePath(string $name): string
    {
        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $name) === 1) {
            return Project::normalize($name);
        }

        return $this->workingDirectory().'/'.$name;
    }

    protected function workingDirectory(): string
    {
        return $this->laravel->make(Project::class)->workingDirectory;
    }

    protected function withAnsi(): bool
    {
        return ! $this->option('no-ansi') || $this->option('ansi');
    }

    protected function bufferedOutput(): Closure
    {
        return function (string $type, string $buffer): void {
            $this->output->write($buffer);
        };
    }

    protected function getLogo(): string
    {
        $logo = trim((new class(app()) extends ConsoleServiceProvider
        {
            public static function getLogo(): string
            {
                return self::logo();
            }
        })::getLogo());

        if (! $this->argument('name')) {
            // If we need to prompt for the name, we trim the empty lines from the logo, so it lays flat.
            return substr($logo, 0, strrpos($logo, "\n", -2));
        }

        return $logo;
    }
}
