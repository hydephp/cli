<?php

declare(strict_types=1);

use App\Support\ComposerBinary;
use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| hyde new
|--------------------------------------------------------------------------
|
| Creating a portable project must work with nothing installed, and asking for
| a Composer project on a machine without Composer must fail before anything
| is written to disk.
|
*/

afterEach(function () {
    ComposerBinary::$fake = null;
    ComposerBinary::$forceMissing = false;
});

it('creates a portable project without touching Composer', function () {
    $workspace = TemporaryProject::directory('workspace');

    $this->boot($workspace);

    // Any attempt to reach for Composer during a portable creation would fail here.
    ComposerBinary::$forceMissing = true;

    expect($this->runCommand('new', ['name' => 'my-site', '--portable' => true, '--no-interaction' => true]))->toBe(0);

    expect($workspace.'/my-site/_pages/index.md')->toBeFile()
        ->and($workspace.'/my-site/hyde.yml')->toBeFile()
        ->and($workspace.'/my-site/_posts')->toBeDirectory()
        ->and($workspace.'/my-site/_media')->toBeDirectory()
        ->and($workspace.'/my-site/composer.json')->not->toBeFile()
        ->and($workspace.'/my-site/vendor')->not->toBeDirectory();
});

it('creates the project relative to the directory the CLI was invoked from', function () {
    $workspace = TemporaryProject::directory('workspace');

    $this->boot($workspace);

    $this->runCommand('new', ['name' => 'nested/site', '--portable' => true, '--no-interaction' => true]);

    expect($workspace.'/nested/site/_pages/index.md')->toBeFile();
});

it('refuses to overwrite a directory that already has something in it', function () {
    $workspace = TemporaryProject::directory('workspace');

    TemporaryProject::write($workspace, ['my-site/important.txt' => "do not lose me\n"]);

    $this->boot($workspace);

    expect($this->runCommand('new', ['name' => 'my-site', '--portable' => true, '--no-interaction' => true]))->not->toBe(0)
        ->and($this->consoleOutput())->toContain('already exists and is not empty')
        ->and(file_get_contents($workspace.'/my-site/important.txt'))->toBe("do not lose me\n");
});

it('defaults to a portable project when it cannot ask', function () {
    $workspace = TemporaryProject::directory('workspace');

    $this->boot($workspace);

    expect($this->runCommand('new', ['name' => 'my-site', '--no-interaction' => true]))->toBe(0)
        ->and($workspace.'/my-site/_pages/index.md')->toBeFile()
        ->and($workspace.'/my-site/composer.json')->not->toBeFile();
});

/*
|--------------------------------------------------------------------------
| Asking for a Composer project without Composer
|--------------------------------------------------------------------------
*/

it('fails with the documented message when Composer is missing', function () {
    $workspace = TemporaryProject::directory('workspace');

    ComposerBinary::$forceMissing = true;

    $this->boot($workspace);

    $status = $this->runCommand('new', ['name' => 'my-site', '--composer' => true, '--no-interaction' => true]);

    expect($status)->not->toBe(0)
        ->and($this->consoleOutput())
        ->toContain('Creating a Composer project requires Composer.')
        ->toContain('hyde new NAME --portable')
        ->toContain('install Composer and retry');
});

it('does not touch the filesystem when Composer is missing', function () {
    $workspace = TemporaryProject::directory('workspace');

    ComposerBinary::$forceMissing = true;

    $this->boot($workspace);

    $this->runCommand('new', ['name' => 'my-site', '--composer' => true, '--no-interaction' => true]);

    expect($workspace.'/my-site')->not->toBeDirectory()
        ->and(scandir($workspace))->toBe(['.', '..']);
});

/*
|--------------------------------------------------------------------------
| Propagating a Composer failure
|--------------------------------------------------------------------------
*/

it('reports a failing Composer run as a failure', function () {
    $workspace = TemporaryProject::directory('workspace');
    $fake = TemporaryProject::directory('fake-composer');

    // Stand in for Composer with something that creates the directory and then fails,
    // which is what a network error or an unresolvable dependency looks like.
    file_put_contents($fake.'/composer', <<<'SH'
    #!/bin/sh
    mkdir -p "$3"
    echo "Creating a \"hyde/hyde\" project"
    echo "Could not resolve dependencies" >&2
    exit 2
    SH);

    chmod($fake.'/composer', 0755);

    ComposerBinary::$fake = $fake.'/composer';

    $this->boot($workspace);

    $status = $this->runCommand('new', ['name' => 'my-site', '--composer' => true, '--no-interaction' => true]);

    expect($status)->toBe(2)
        ->and($this->consoleOutput())->toContain('Composer exited with code 2');
})->skipOnWindows();

it('removes the partial directory when Composer fails midway', function () {
    $workspace = TemporaryProject::directory('workspace');
    $fake = TemporaryProject::directory('fake-composer');

    file_put_contents($fake.'/composer', <<<'SH'
    #!/bin/sh
    mkdir -p "$3/vendor"
    echo "half a project" > "$3/composer.json"
    exit 1
    SH);

    chmod($fake.'/composer', 0755);

    ComposerBinary::$fake = $fake.'/composer';

    $this->boot($workspace);

    $this->runCommand('new', ['name' => 'my-site', '--composer' => true, '--no-interaction' => true]);

    expect($workspace.'/my-site')->not->toBeDirectory();
})->skipOnWindows();

it('reports success only when Composer succeeded', function () {
    $workspace = TemporaryProject::directory('workspace');
    $fake = TemporaryProject::directory('fake-composer');

    file_put_contents($fake.'/composer', <<<'SH'
    #!/bin/sh
    mkdir -p "$3"
    echo '{"name":"acme/site","require":{"hyde/framework":"^2.0"}}' > "$3/composer.json"
    exit 0
    SH);

    chmod($fake.'/composer', 0755);

    ComposerBinary::$fake = $fake.'/composer';

    $this->boot($workspace);

    expect($this->runCommand('new', ['name' => 'my-site', '--composer' => true, '--no-interaction' => true]))->toBe(0)
        ->and($this->consoleOutput())->toContain('Created a Hyde Composer project')
        ->and($workspace.'/my-site/composer.json')->toBeFile();
})->skipOnWindows();

/*
|--------------------------------------------------------------------------
| The project major follows the executable's major
|--------------------------------------------------------------------------
|
| A HydeCLI 3.x binary must create Hyde 3.x projects and nothing else. An
| unconstrained `create-project` would have this executable creating a Hyde 4
| project on the day Hyde 4 became the latest stable release.
|
*/

it('pins the created project to the major this executable supports', function () {
    $workspace = TemporaryProject::directory('workspace');
    $fake = TemporaryProject::directory('fake-composer');

    file_put_contents($fake.'/composer', <<<'SH'
    #!/bin/sh
    mkdir -p "$3"
    printf '%s\n' "$@" > "$3/argv.txt"
    exit 0
    SH);

    chmod($fake.'/composer', 0755);

    ComposerBinary::$fake = $fake.'/composer';

    $this->boot($workspace);

    expect($this->runCommand('new', ['name' => 'my-site', '--composer' => true, '--no-interaction' => true]))->toBe(0);

    $argv = explode("\n", trim(file_get_contents($workspace.'/my-site/argv.txt')));

    $major = explode('.', App\Application::APP_VERSION)[0];

    expect($argv)->toContain('hyde/hyde')
        ->and($argv)->toContain("^$major.0")
        // Nothing may reach Packagist unconstrained, whatever the current major is.
        ->and($argv)->not->toContain('*')
        ->and($argv)->not->toContain('dev-master');
})->skipOnWindows();

it('creates from the development line when a source is configured', function () {
    $workspace = TemporaryProject::directory('workspace');
    $fake = TemporaryProject::directory('fake-composer');

    file_put_contents($fake.'/composer', <<<'SH'
    #!/bin/sh
    mkdir -p "$3"
    printf '%s\n' "$@" > "$3/argv.txt"
    exit 0
    SH);

    chmod($fake.'/composer', 0755);

    ComposerBinary::$fake = $fake.'/composer';
    putenv('HYDE_PROJECT_SOURCE=/tmp/hyde-v3-template');

    $this->boot($workspace);

    expect($this->runCommand('new', ['name' => 'my-site', '--composer' => true, '--no-interaction' => true]))->toBe(0);

    $argv = file_get_contents($workspace.'/my-site/argv.txt');

    // The override replaces the released constraint rather than sitting alongside it,
    // so a development run can never resolve a published package by accident.
    expect($argv)->toContain('3.0.0-dev')
        ->toContain('--stability=dev')
        ->toContain('/tmp/hyde-v3-template')
        ->not->toContain('^3.0');

    putenv('HYDE_PROJECT_SOURCE');
})->skipOnWindows();
