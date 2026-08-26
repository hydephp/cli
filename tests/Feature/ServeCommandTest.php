<?php

declare(strict_types=1);

use App\Commands\ServeCommand;
use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| hyde serve
|--------------------------------------------------------------------------
|
| The command is proven end to end by the integration suite, which starts the
| real server from the built executable and makes real HTTP requests against
| it. What is asserted here is the part that cannot be observed from outside:
| that the server is never started with a `php` resolved from the search path.
|
*/

/** A serve command that reports what it would do instead of doing it. */
function inspectableServeCommand(mixed $application): ServeCommand
{
    $command = new class() extends ServeCommand
    {
        /** @param string|null $key */
        public function option($key = null): mixed
        {
            return null; // Every option is left at its default.
        }

        public function serverCommand(): string
        {
            return sprintf('%s -S %s:%d %s',
                escapeshellarg($this->runtime()->path()),
                $this->getHostSelection(),
                $this->getPortSelection(),
                escapeshellarg($this->getExecutablePath()),
            );
        }

        public function environment(): array
        {
            return $this->getEnvironmentVariables();
        }
    };

    $command->setLaravel($application);

    return $command;
}

it('starts the server with a runtime it resolved, never a bare php', function () {
    $this->boot(TemporaryProject::portable());

    $command = inspectableServeCommand($this->app)->serverCommand();

    expect($command)->not->toStartWith('php ')
        ->and($command)->not->toStartWith("'php'")
        ->and($command)->toContain(' -S ')
        ->and($command)->toContain('server.php');
});

it('tells the server process where the application lives', function () {
    $path = TemporaryProject::portable();

    $this->boot($path);

    $environment = inspectableServeCommand($this->app)->environment();

    expect($environment)->toHaveKeys(['HYDE_AUTOLOAD_PATH', 'HYDE_BOOTSTRAP_PATH', 'HYDE_WORKING_DIR', 'HYDE_TEMP_DIR', 'HYDE_BUNDLED_STYLESHEET'])
        ->and($environment['HYDE_WORKING_DIR'])->toBe($path)
        ->and($environment['HYDE_AUTOLOAD_PATH'])->toEndWith('vendor/autoload.php')
        ->and($environment['HYDE_BOOTSTRAP_PATH'])->toEndWith('app/bootstrap.php');
});

it('keeps its temporary files out of the project', function () {
    $path = TemporaryProject::portable();

    $this->boot($path);

    expect(inspectableServeCommand($this->app)->environment()['HYDE_TEMP_DIR'])->not->toStartWith($path);
});
