<?php

declare(strict_types=1);

namespace App\Commands;

use Closure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Hyde\Console\ConsoleServiceProvider;

use function Laravel\Prompts\text;

/**
 * Creates a new Hyde project.
 */
class NewProjectCommand extends Command
{
    /** @var string */
    protected $signature = 'new {name? : The name of the project}';

    /** @var string */
    protected $description = 'Create a new Hyde project.';

    public function handle(): void
    {
        $this->output->write($this->withAnsi() ? $this->getLogo() : 'Welcome to HydePHP!');

        $name = $this->argument('name') ?? text('What is the name of your project?', required: 'Please provide a name for your project.');

        Process::command($this->getCommand($name))
            ->run(null, $this->bufferedOutput());

        $this->newLine();
        $this->info('Project created successfully. Build something awesome!');
    }

    protected function getCommand(string $name): string
    {
        return trim(sprintf('composer create-project hyde/hyde %s --prefer-dist %s', $name, $this->withAnsi() ? '--ansi' : '--no-ansi'));
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
