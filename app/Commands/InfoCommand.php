<?php

declare(strict_types=1);

namespace App\Commands;

use App\Application;
use App\Launcher\Project;
use App\Launcher\Platform;
use App\Launcher\ProjectType;
use App\Launcher\RuntimeManager;
use App\Launcher\ComposerManifest;
use App\Support\FrameworkGeneration;
use Illuminate\Console\Command;

use function max;
use function implode;
use function sprintf;
use function str_pad;
use function array_keys;
use function extension_loaded;

/**
 * Reports what this executable is, and what it is about to run against.
 *
 * The CLI version and the framework version are allowed to differ: in Composer
 * mode the framework belongs to the project, not to the executable.
 */
class InfoCommand extends Command
{
    /** @var string */
    protected $signature = 'info';

    /** @var string */
    protected $description = 'Show information about the CLI and the current project.';

    public function handle(): int
    {
        $rows = $this->rows();

        // Pad the labels so the values line up in a single column.
        $width = max(array_map('strlen', array_keys($rows))) + 1;

        $this->newLine();

        foreach ($rows as $label => $value) {
            $this->line(sprintf('<info>%s</info> %s', str_pad($label.':', $width), $value));
        }

        $this->newLine();

        if ($this->output->isVerbose()) {
            $this->printCompatibilityDetails();
        }

        return Command::SUCCESS;
    }

    /** @return array<string, string> */
    protected function rows(): array
    {
        $project = $this->project();

        $rows = [
            'Hyde CLI' => Application::APP_VERSION,
            'Project type' => $project->type->label(),
            'Framework' => $this->framework($project),
            'PHP' => $this->php(),
        ];

        if ($project->type === ProjectType::Composer) {
            $rows['Dependencies'] = './vendor/autoload.php';
        }

        $rows['Root'] = $project->root;

        return $rows;
    }

    /**
     * The framework version, and where it comes from.
     *
     * In Composer mode this is read out of the project's own lock file, without
     * loading the project's autoloader, since the two dependency graphs are
     * never allowed to share a process.
     */
    protected function framework(Project $project): string
    {
        if ($project->type === ProjectType::Portable) {
            return sprintf('%s (embedded)', FrameworkGeneration::describe());
        }

        $version = ComposerManifest::lockedVersion($project->root.'/composer.lock');

        if ($version === null) {
            $constraint = ComposerManifest::read($project->composerFile)->hydeRequirements();

            $version = $constraint === [] ? 'unknown' : implode(', ', $constraint);
        }

        return sprintf('%s (project)', $version);
    }

    protected function php(): string
    {
        $runtime = $this->runtime();

        return sprintf('%s (%s)', PHP_VERSION, $runtime->isBundled() ? 'bundled' : 'system');
    }

    protected function printCompatibilityDetails(): void
    {
        $runtime = $this->runtime();
        $platform = Platform::current();

        $this->line('<info>Platform:</info>     '.$platform->slug());
        $this->line('<info>SAPI:</info>         '.PHP_SAPI);
        $this->line('<info>Runtime:</info>      '.($runtime->hasEmbeddedRuntime()
            ? sprintf('PHP %s bundled for %s', $runtime->manifest()['version'], $runtime->manifest()['platform'])
            : 'none bundled (running from a source checkout)'));
        $this->line('<info>Extensions:</info>   '.implode(', ', $this->loadedExtensions()));
        $this->newLine();
    }

    /** @return list<string> */
    protected function loadedExtensions(): array
    {
        $required = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'mbstring', 'openssl', 'phar', 'simplexml', 'tokenizer', 'xml', 'zlib'];

        return array_map(fn (string $extension): string => extension_loaded($extension) ? $extension : "<fg=red>$extension (missing)</>", $required);
    }

    protected function project(): Project
    {
        return $this->laravel->make(Project::class);
    }

    protected function runtime(): RuntimeManager
    {
        return $this->laravel->make(RuntimeManager::class);
    }
}
