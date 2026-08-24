<?php

declare(strict_types=1);

use Tests\Support\Server;
use Tests\Support\Executable;
use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| Dispatching into a Composer project
|--------------------------------------------------------------------------
|
| The fixture is a real Hyde Composer project with its dependencies installed
| and a path-repository addon package. The addon registers a command that
| exists only in that project's dependency graph, which makes it a probe
| for isolation that no version string could provide.
|
*/

const FIXTURE = __DIR__.'/../Fixtures/composer-project';

beforeEach(function () {
    if (Executable::path() === null) {
        $this->markTestSkipped(Executable::missingMessage());
    }

    if (! is_file(FIXTURE.'/vendor/autoload.php')) {
        $this->markTestSkipped('Run `composer install` in tests/Fixtures/composer-project first.');
    }
});

it('dispatches into the project rather than running the embedded application', function () {
    $result = Executable::run(['--version', '--no-ansi'], FIXTURE);

    // The project prints its own version banner, built by its own application. The
    // executable's banner names both versions at once, so that composite is what has to
    // be absent — the numbers themselves are no longer a discriminator now that the CLI
    // and the framework are both on 3.0.
    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain(Hyde\Foundation\HydeKernel::VERSION)
        ->and($result['output'])->not->toContain('- (HydePHP');
});

it('executes a command that only the project\'s own dependency graph provides', function () {
    $result = Executable::run(['test:addon', '--no-ansi'], FIXTURE);

    expect($result['status'])->toBe(0)
        ->and($result['output'])
        ->toContain('Addon command executed')
        ->toContain('vendor/hyde/cli-test-addon')
        ->toContain('Project root: '.realpath(FIXTURE));
});

it('does not expose the project-only command to a portable project', function () {
    $path = TemporaryProject::portable();

    $listed = Executable::run(['list', '--no-ansi'], $path);

    expect($listed['output'])->not->toContain('test:addon');

    $invoked = Executable::run(['test:addon', '--no-ansi'], $path);

    // The command must be genuinely absent, and asking for it must be a failure rather
    // than a command listing printed with a successful exit status.
    expect($invoked['status'])->not->toBe(0)
        ->and($invoked['output'])->toContain('no commands defined in the "test" namespace')
        ->and($invoked['output'])->not->toContain('Addon command executed');
});

it('reports the project as a Composer project, with the framework it pins', function () {
    $result = Executable::run(['info', '--no-ansi'], FIXTURE);

    expect($result['status'])->toBe(0)
        ->and($result['output'])
        ->toContain('Project type: Composer')
        ->toContain('(project)')
        ->toContain('Dependencies: ./vendor/autoload.php')
        ->toContain(realpath(FIXTURE));
});

it('builds the project through its own framework', function () {
    $result = Executable::run(['build', '--no-ansi'], FIXTURE);

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('Your static site has been built!')
        ->and(FIXTURE.'/_site/index.html')->toBeFile()
        ->and(file_get_contents(FIXTURE.'/_site/index.html'))->toContain('Composer fixture project');
});

it('propagates a failing exit status from the project', function () {
    $result = Executable::run(['test:fail', '--code=17', '--no-ansi'], FIXTURE);

    expect($result['status'])->toBe(17)
        ->and($result['output'])->toContain('Addon command failing on purpose');
});

it('serves the project with the bundled runtime, on a machine with no PHP', function () {
    $server = Server::start(FIXTURE);

    try {
        expect($server->waitUntilReady())->toBeTrue($server->output());

        // The project's own serve command shells out to a bare `php`. There is none on the
        // search path the executable was given, so the one it answers with is the one
        // the launcher put there: the runtime bundled inside the executable.
        expect($server->get('/'))->toContain('Composer fixture project')
            ->and($server->output())->toContain('Development Server');
    } finally {
        $server->stop();
    }
});

it('dispatches from a subdirectory of the project', function () {
    $result = Executable::run(['test:addon', '--no-ansi'], FIXTURE.'/_pages');

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('Addon command executed');
});

/*
|--------------------------------------------------------------------------
| The dispatched project runs HydePHP v3
|--------------------------------------------------------------------------
|
| The fixture resolves the framework from the develop@master monorepo through a
| Composer path repository, so what runs here is the v3 development line rather
| than a published package. These assert on behaviour that differs between the
| two lines, since the version string does not.
|
*/

it('dispatches into a project whose own dependency graph is v3', function () {
    $result = Executable::run(['list', '--no-ansi'], FIXTURE);

    // The command list belongs to the project's framework, not the executable's.
    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('build')
        ->and($result['output'])->not->toContain('rebuild');
});

it('builds the project with the v3 markdown pipeline', function () {
    file_put_contents(FIXTURE.'/_pages/v3-probe.md', <<<'MD'
    # Probe

    ```php title="app/Model.php"
    echo 'Hello World!';
    ```
    MD);

    try {
        $result = Executable::run(['build', '--no-ansi'], FIXTURE);

        expect($result['status'])->toBe(0);

        // The title modifier and the code block view are both v3; a v2 project would
        // have left the modifier in the info string and rendered no label at all.
        expect(file_get_contents(FIXTURE.'/_site/v3-probe.html'))
            ->toContain('hyde-code-block-label')
            ->toContain('app/Model.php');
    } finally {
        @unlink(FIXTURE.'/_pages/v3-probe.md');
        @unlink(FIXTURE.'/_site/v3-probe.html');
    }
});
