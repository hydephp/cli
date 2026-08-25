<?php

declare(strict_types=1);

use App\Launcher\Project;
use App\Launcher\Launcher;
use App\Launcher\ProjectType;
use App\Launcher\ProjectDetector;
use App\Launcher\ProjectDispatcher;
use App\Launcher\RuntimeDispatcher;
use App\Launcher\LauncherException;
use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| Command routing
|--------------------------------------------------------------------------
|
| The launcher decides, before any autoloader exists, whether a call belongs to
| the executable or to the project it was invoked inside.
|
*/

it('reads the command name past any options', function (array $argv, ?string $expected) {
    expect((new Launcher())->commandName($argv))->toBe($expected);
})->with([
    [['hyde'], null],
    [['hyde', 'build'], 'build'],
    [['hyde', '--version'], null],
    [['hyde', '--no-ansi', 'build'], 'build'],
    [['hyde', '-v', 'route:list', '--json'], 'route:list'],
]);

it('does not mistake the value of a global option for the command', function (array $argv, ?string $expected) {
    // `--env` takes its value as the next token, so that token is not a command name.
    // Reading it as one would route the call to a command nobody typed.
    expect((new Launcher())->commandName($argv))->toBe($expected);
})->with([
    'separate value' => [['hyde', '--env', 'development', 'php'], 'php'],
    'joined value' => [['hyde', '--env=development', 'php'], 'php'],
    'separate value, then a project command' => [['hyde', '--env', 'development', 'build'], 'build'],
    'among other options' => [['hyde', '--no-ansi', '--env', 'development', '-v', 'composer'], 'composer'],
    'nothing after the value' => [['hyde', '--env', 'development'], null],
    'no value to take' => [['hyde', '--env'], null],
]);

it('takes what follows a bare double dash as the command', function () {
    expect((new Launcher())->commandName(['hyde', '--', 'php']))->toBe('php')
        ->and((new Launcher())->commandName(['hyde', '--']))->toBeNull();
});

it('keeps the CLI-owned commands for the executable', function (string $command) {
    expect((new Launcher())->isLauncherCommand(['hyde', $command]))->toBeTrue();
})->with(['info', 'new', 'self-update']);

it('treats every other command as belonging to the project', function (string $command) {
    expect((new Launcher())->isLauncherCommand(['hyde', $command]))->toBeFalse();
})->with(['build', 'serve', 'route:list', 'make:page', 'test:addon']);

it('keeps the bundled programs for the executable', function (string $command) {
    expect((new Launcher())->isRuntimeCommand(['hyde', $command]))->toBeTrue();
})->with(Launcher::RUNTIME_COMMANDS);

it('does not treat a project command as a bundled program', function (string $command) {
    expect((new Launcher())->isRuntimeCommand(['hyde', $command]))->toBeFalse();
})->with(['build', 'serve', 'info', 'new', 'self-update']);

it('forwards everything typed after the command name', function (array $argv, array $expected) {
    expect((new Launcher())->argumentsFor($argv))->toBe($expected);
})->with([
    'nothing to forward' => [['hyde', 'php'], []],
    'an option for the program' => [['hyde', 'php', '-v'], ['-v']],
    'a script and its arguments' => [['hyde', 'php', 'script.php', '--flag', 'value'], ['script.php', '--flag', 'value']],
    'an option for the CLI is not forwarded' => [['hyde', '-v', 'php', '-r', 'echo 1;'], ['-r', 'echo 1;']],
    'nor is one that took a value' => [['hyde', '--env', 'development', 'php', '-r', 'echo 1;'], ['-r', 'echo 1;']],
    'nor is one that carried its value' => [['hyde', '--env=development', 'php', '-v'], ['-v']],
    'no command at all' => [['hyde', '--version'], []],
]);

/*
|--------------------------------------------------------------------------
| Dispatching
|--------------------------------------------------------------------------
*/

it('dispatches a composer project into its own entry point', function () {
    $path = TemporaryProject::composer(['hyde' => "<?php\n"]);

    $dispatcher = new class() extends ProjectDispatcher
    {
        public ?Project $dispatched = null;

        /** @var list<string> */
        public array $arguments = [];

        public function dispatch(Project $project, array $arguments = []): int
        {
            $this->dispatched = $project;
            $this->arguments = $arguments;

            return 7;
        }
    };

    $launcher = new Launcher(new ProjectDetector(), $dispatcher);

    putenv("HYDE_WORKING_DIR=$path");

    $status = $launcher->run(['hyde', 'build', '--no-ansi']);

    expect($status)->toBe(7)
        ->and($dispatcher->dispatched?->root)->toBe($path)
        ->and($dispatcher->arguments)->toBe(['build', '--no-ansi']);
});

it('does not dispatch a portable project', function () {
    $path = TemporaryProject::portable();

    $dispatcher = new class() extends ProjectDispatcher
    {
        public bool $called = false;

        public function dispatch(Project $project, array $arguments = []): int
        {
            $this->called = true;

            return 0;
        }
    };

    putenv("HYDE_WORKING_DIR=$path");

    expect((new Launcher(new ProjectDetector(), $dispatcher))->run(['hyde', 'build']))->toBeNull()
        ->and($dispatcher->called)->toBeFalse();
});

it('refuses project commands inside an unrelated composer project', function () {
    $path = TemporaryProject::directory();

    TemporaryProject::write($path, [
        'composer.json' => '{"name":"laravel/laravel","require":{"laravel/framework":"^13.0"}}',
    ]);

    putenv("HYDE_WORKING_DIR=$path");

    expect(fn () => (new Launcher())->run(['hyde', 'build']))
        ->toThrow(
            LauncherException::class,
            'This is a Composer project, but it does not declare Hyde.'
        );
});

it('keeps launcher-owned commands available inside an unrelated composer project', function () {
    $path = TemporaryProject::directory();

    TemporaryProject::write($path, [
        'composer.json' => '{"name":"laravel/laravel","require":{"laravel/framework":"^13.0"}}',
    ]);

    $dispatcher = new class() extends ProjectDispatcher
    {
        public bool $called = false;

        public function dispatch(Project $project, array $arguments = []): int
        {
            $this->called = true;

            return 0;
        }
    };

    putenv("HYDE_WORKING_DIR=$path");

    expect((new Launcher(new ProjectDetector(), $dispatcher))->run(['hyde', 'info']))->toBeNull()
        ->and($dispatcher->called)->toBeFalse();
});

it('does not dispatch a CLI-owned command inside a composer project', function () {
    $path = TemporaryProject::composer(['hyde' => "<?php\n"]);

    $dispatcher = new class() extends ProjectDispatcher
    {
        public bool $called = false;

        public function dispatch(Project $project, array $arguments = []): int
        {
            $this->called = true;

            return 0;
        }
    };

    putenv("HYDE_WORKING_DIR=$path");

    expect((new Launcher(new ProjectDetector(), $dispatcher))->run(['hyde', 'info']))->toBeNull()
        ->and($dispatcher->called)->toBeFalse();
});

it('answers a bundled program without dispatching, even in a broken composer project', function () {
    // This is the whole point of answering these before detection: `hyde composer install`
    // has to work in the one project state the launcher otherwise refuses to run in.
    $path = TemporaryProject::composer(withAutoloader: false);

    $dispatcher = new class() extends ProjectDispatcher
    {
        public bool $called = false;

        public function dispatch(Project $project, array $arguments = []): int
        {
            $this->called = true;

            return 0;
        }
    };

    $runtime = new class() extends RuntimeDispatcher
    {
        public ?string $command = null;

        /** @var list<string> */
        public array $arguments = [];

        public function run(string $command, array $arguments = []): int
        {
            $this->command = $command;
            $this->arguments = $arguments;

            return 4;
        }
    };

    putenv("HYDE_WORKING_DIR=$path");

    $status = (new Launcher(new ProjectDetector(), $dispatcher, $runtime))->run(['hyde', 'php', '-r', 'echo 1;']);

    expect($status)->toBe(4)
        ->and($runtime->command)->toBe('php')
        ->and($runtime->arguments)->toBe(['-r', 'echo 1;'])
        ->and($dispatcher->called)->toBeFalse();
});

it('refuses to dispatch into itself', function () {
    // The CLI's own checkout is a Hyde Composer project whose entry point is the very
    // file that is running, and dispatching into it would recurse forever.
    $launcher = new Launcher();

    $path = TemporaryProject::directory('self');

    symlink((string) $launcher->selfPath(), $path.'/hyde');

    expect($launcher->isSelfDispatch(Project::composer($path, $path)))->toBeTrue();
})->skipOnWindows();

it('does not consider another project a self dispatch', function () {
    $path = TemporaryProject::composer(['hyde' => "<?php\n"]);

    expect((new Launcher())->isSelfDispatch(Project::composer($path, $path)))->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| The environment the application boots against
|--------------------------------------------------------------------------
*/

it('detects the project once and remembers it', function () {
    $path = TemporaryProject::portable();

    putenv("HYDE_WORKING_DIR=$path");

    $launcher = new Launcher();

    expect($launcher->detect())->toBe($launcher->detect())
        ->and(Launcher::project()->type)->toBe(ProjectType::Portable);
});

it('honours an explicit working directory', function () {
    $path = TemporaryProject::portable();

    putenv("HYDE_WORKING_DIR=$path");

    expect((new Launcher())->workingDirectory())->toBe($path);
});

it('falls back to the current directory', function () {
    putenv('HYDE_WORKING_DIR');

    expect((new Launcher())->workingDirectory())->toBe(Project::normalize(getcwd()));
});

it('reports launcher failures on standard error with a non-zero status', function () {
    expect((new Launcher())->fail(new App\Launcher\LauncherException('Something went wrong', 3)))->toBe(3);
});

it('never reports a launcher failure as a success', function () {
    expect((new Launcher())->fail(new App\Launcher\LauncherException('Something went wrong', 0)))->toBe(1);
});
