<?php

declare(strict_types=1);

use App\Launcher\Project;
use App\Launcher\Launcher;
use App\Launcher\ProjectType;
use App\Launcher\ProjectDetector;
use App\Launcher\ProjectDispatcher;
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

it('keeps the CLI-owned commands for the executable', function (string $command) {
    expect((new Launcher())->isLauncherCommand(['hyde', $command]))->toBeTrue();
})->with(['info', 'new', 'self-update']);

it('treats every other command as belonging to the project', function (string $command) {
    expect((new Launcher())->isLauncherCommand(['hyde', $command]))->toBeFalse();
})->with(['build', 'serve', 'route:list', 'make:page', 'test:addon']);

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
