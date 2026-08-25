<?php

declare(strict_types=1);

use App\Launcher\Project;
use App\Launcher\RuntimeManager;
use App\Launcher\ProjectDispatcher;
use App\Launcher\LauncherException;
use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| Refusing to run a broken Composer project
|--------------------------------------------------------------------------
|
| This is the invariant the whole project model rests on: a Hyde Composer
| project whose dependencies are missing must fail, and must never quietly
| be built with the framework bundled inside the executable.
|
*/

it('refuses a composer project with no vendor directory', function () {
    $path = TemporaryProject::composer(withAutoloader: false);

    $project = Project::composer($path, $path);

    expect(fn () => (new ProjectDispatcher())->guardAgainstBrokenInstall($project))
        ->toThrow(LauncherException::class, 'dependencies are not installed');
});

it('names the autoloader it expected and the command that fixes it', function () {
    $path = TemporaryProject::composer(withAutoloader: false);

    try {
        (new ProjectDispatcher())->guardAgainstBrokenInstall(Project::composer($path, $path));
    } catch (LauncherException $exception) {
        expect($exception->getMessage())
            ->toContain($path.'/vendor/autoload.php')
            ->toContain('composer install')
            ->toContain('Nothing has been changed.')
            ->and($exception->status)->not->toBe(0);

        return;
    }

    $this->fail('The dispatcher accepted a project with no autoloader.');
});

it('refuses a composer project with no entry point', function () {
    $path = TemporaryProject::composer();

    expect(fn () => (new ProjectDispatcher())->guardAgainstBrokenInstall(Project::composer($path, $path)))
        ->toThrow(LauncherException::class, 'no `hyde` executable');
});

it('accepts a composer project that is fully installed', function () {
    $path = TemporaryProject::composer(['hyde' => "<?php\n"]);

    (new ProjectDispatcher())->guardAgainstBrokenInstall(Project::composer($path, $path));
})->throwsNoExceptions();

it('refuses to dispatch a portable project', function () {
    $path = TemporaryProject::portable();

    expect(fn () => (new ProjectDispatcher())->guardAgainstBrokenInstall(Project::portable($path)))
        ->toThrow(LauncherException::class, 'Only Composer projects can be dispatched');
});

/*
|--------------------------------------------------------------------------
| The environment the project is given
|--------------------------------------------------------------------------
*/

it('puts the bundled runtime at the front of the search path', function () {
    $dispatcher = new class() extends ProjectDispatcher
    {
        public function environmentFor(string $entryPoint): array
        {
            return $this->environment($entryPoint);
        }
    };

    $environment = $dispatcher->environmentFor('/projects/site/hyde');

    // Read back under the key the platform actually uses, which on Windows is `Path`.
    // Asserting on a hard-coded `PATH` would be reading a key that is not there.
    $key = $dispatcher->searchPathKey($environment);

    // A Hyde project shells out to a bare `php`, so the runtime the executable carries has
    // to be findable by the project's own subprocesses.
    expect($environment[$key])->toStartWith(dirname((new RuntimeManager())->path()))
        ->and($environment[$key])->toContain((string) getenv($key))
        ->and($environment[ProjectDispatcher::DISPATCH_MARKER])->toBe('/projects/site/hyde');
});

it('starts the project with the bundled runtime, not through a shebang', function () {
    $command = (new ProjectDispatcher())->command('/projects/site/hyde', ['build', '--no-ansi']);

    // Which PHP runs the project is decided here, by naming the interpreter, rather than
    // left to whatever a shebang would resolve `php` to on the host.
    expect($command[0])->toBe((new RuntimeManager())->path())
        ->and($command[0])->not->toBe('php')
        ->and($command)->toBe([(new RuntimeManager())->path(), '/projects/site/hyde', 'build', '--no-ansi']);
});

it('extends the search path Windows actually uses', function () {
    $dispatcher = new ProjectDispatcher();

    // Windows stores it as `Path`; adding a second `PATH` key would leave the child with
    // two search paths, and the one the user set would very likely be the one ignored.
    expect($dispatcher->searchPathKey(['Path' => 'C:\\Windows', 'HOME' => 'C:\\Users\\emma']))->toBe('Path')
        ->and($dispatcher->searchPathKey(['PATH' => '/usr/bin']))->toBe('PATH')
        ->and($dispatcher->searchPathKey(['path' => '/usr/bin']))->toBe('path')
        ->and($dispatcher->searchPathKey(['HOME' => '/home/emma']))->toBe('PATH');
});

/*
|--------------------------------------------------------------------------
| Running the project
|--------------------------------------------------------------------------
*/

it('runs the project entry point and returns its exit status', function () {
    $path = TemporaryProject::composer(['hyde' => <<<'PHP'
    <?php

    file_put_contents(__DIR__.'/dispatch.log', implode("\n", [
        'arguments: '.implode(' ', array_slice($argv, 1)),
        'cwd: '.getcwd(),
        'autoloader: '.(is_file(getcwd().'/vendor/autoload.php') ? 'present' : 'missing'),
        'marker: '.(getenv('HYDE_DISPATCHED_INTO') ?: 'none'),
    ]));

    exit(3);
    PHP]);

    // The child inherits this process's standard streams, so it records what it saw to a
    // file. That is also the point: nothing is proxied or interpreted on the way.
    $status = (new ProjectDispatcher(new RuntimeManager()))->dispatch(Project::composer($path, $path), ['build', '--no-ansi']);

    // The child reports its working directory in the host's own spelling, since that is
    // what `getcwd()` hands it. What is asserted is which directory it ran in, so the
    // separators are brought to the canonical one before the paths are compared.
    $log = str_replace('\\', '/', file_get_contents($path.'/dispatch.log'));

    expect($status)->toBe(3)
        ->and($log)
        ->toContain('arguments: build --no-ansi')
        ->toContain('cwd: '.$path)
        ->toContain('autoloader: present')
        ->toContain('marker: '.$path.'/hyde');
});

it('returns a successful status when the project succeeds', function () {
    $path = TemporaryProject::composer(['hyde' => "<?php\n\nexit(0);\n"]);

    expect((new ProjectDispatcher(new RuntimeManager()))->dispatch(Project::composer($path, $path)))->toBe(0);
});

it('propagates a failing status from the project rather than reporting success', function () {
    $path = TemporaryProject::composer(['hyde' => "<?php\n\nexit(42);\n"]);

    expect((new ProjectDispatcher(new RuntimeManager()))->dispatch(Project::composer($path, $path)))->toBe(42);
});

it('refuses to dispatch back into the entry point that dispatched it', function () {
    $path = TemporaryProject::composer(['hyde' => "<?php\n"]);

    putenv('HYDE_DISPATCHED_INTO='.$path.'/hyde');

    try {
        expect(fn () => (new ProjectDispatcher())->dispatch(Project::composer($path, $path)))
            ->toThrow(LauncherException::class, 'would loop forever');
    } finally {
        putenv('HYDE_DISPATCHED_INTO');
    }
});
