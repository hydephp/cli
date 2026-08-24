<?php

declare(strict_types=1);

use App\Support\ComposerBinary;
use Tests\Support\Executable;
use Tests\Support\ProjectTemplate;
use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| Creating a Composer project from the v3 development line
|--------------------------------------------------------------------------
|
| `composer create-project hyde/hyde` resolves the published project, which is
| still the v2 line while v3 is unreleased. Creating a project from it would be
| no evidence of v3 support at all, so the command is pointed at a local v3
| source through `HYDE_PROJECT_SOURCE` and the result is inspected.
|
| This runs the real executable and a real Composer install. Only the absence of
| a built artifact skips it, which is what the whole Integration suite does; a
| missing template or a missing Composer is a hard failure, because a green run
| that quietly left this out would be worse than a red one.
|
*/

beforeEach(function () {
    if (Executable::path() === null) {
        $this->markTestSkipped(Executable::missingMessage());
    }

    // Not a skip: the suite is run with Composer-installed dev dependencies, so a host
    // without Composer is a broken environment rather than an unsupported one.
    expect(ComposerBinary::locate())->not->toBeNull('Composer is required to run the integration suite.');
});

it('creates a project running the v3 development dependency graph', function () {
    $workspace = TemporaryProject::directory('workspace');
    $template = ProjectTemplate::path();

    $result = Executable::run(
        ['new', 'my-site', '--composer', '--no-ansi', '--no-interaction'],
        $workspace,
        ['HYDE_PROJECT_SOURCE' => $template, 'PATH' => getenv('PATH')],
    );

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('Created a Hyde Composer project');

    $lock = json_decode((string) file_get_contents($workspace.'/my-site/composer.lock'), true);

    $packages = [];

    foreach ($lock['packages'] ?? [] as $package) {
        $packages[$package['name']] = $package;
    }

    // The created project owns this graph; the executable installed it and then let go.
    // A `path` dist is what proves the monorepo won the resolution rather than Packagist,
    // which would have supplied the v2 line under a version number that looks the same.
    expect($packages)->toHaveKey('hyde/framework')
        ->and($packages['hyde/framework']['dist']['type'])->toBe('path')
        ->and($packages['hyde/framework']['version'])->toBe('dev-master')
        ->and($packages['hyde/realtime-compiler']['dist']['type'])->toBe('path');

    // And the framework that landed there carries v3's removal.
    expect($workspace.'/my-site/vendor/hyde/framework/src/Console/Commands/RebuildPageCommand.php')
        ->not->toBeFile();
});

it('builds the created project through the v3 markdown pipeline', function () {
    $workspace = TemporaryProject::directory('workspace');
    $template = ProjectTemplate::path();

    Executable::run(
        ['new', 'my-site', '--composer', '--no-ansi', '--no-interaction'],
        $workspace,
        ['HYDE_PROJECT_SOURCE' => $template, 'PATH' => getenv('PATH')],
    );

    file_put_contents($workspace.'/my-site/_pages/index.md', <<<'MD'
    # Probe

    ```php title="app/Model.php"
    echo 'Hello World!';
    ```
    MD);

    // The executable dispatches into the project it just created, which runs on its own
    // dependency graph rather than on the framework embedded in the executable.
    $result = Executable::run(['build', '--no-ansi'], $workspace.'/my-site');

    expect($result['status'])->toBe(0)
        ->and(file_get_contents($workspace.'/my-site/_site/index.html'))
        ->toContain('hyde-code-block-label')
        ->toContain('app/Model.php');
});
