<?php

declare(strict_types=1);

use App\Launcher\Project;
use App\Launcher\ProjectType;
use App\Launcher\ProjectDetector;
use App\Launcher\LauncherException;
use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| Project detection
|--------------------------------------------------------------------------
|
| Detection decides which dependency graph a command runs against, so it is
| tested against real directories rather than mocked filesystem state.
|
*/

it('detects a directory with content but no manifest as portable', function () {
    $path = TemporaryProject::portable();

    expect((new ProjectDetector())->detect($path))
        ->type->toBe(ProjectType::Portable)
        ->root->toBe($path);
});

it('detects an empty directory as portable', function () {
    $path = TemporaryProject::directory();

    expect((new ProjectDetector())->detect($path)->type)->toBe(ProjectType::Portable);
});

it('detects a manifest that requires the framework as a composer project', function () {
    $path = TemporaryProject::composer();

    expect((new ProjectDetector())->detect($path))
        ->type->toBe(ProjectType::Composer)
        ->root->toBe($path)
        ->composerFile->toBe($path.'/composer.json');
});

it('detects a manifest that requires the hyde project package as a composer project', function () {
    $path = TemporaryProject::composer(manifest: '{"name": "acme/site", "require": {"hyde/hyde": "^2.0"}}');

    expect((new ProjectDetector())->detect($path)->type)->toBe(ProjectType::Composer);
});

it('treats a require-dev only requirement as a composer project', function () {
    $path = TemporaryProject::composer(manifest: '{"name": "acme/site", "require-dev": {"hyde/framework": "^2.0"}}');

    expect((new ProjectDetector())->detect($path)->type)->toBe(ProjectType::Composer);
});

it('is case insensitive about package names', function () {
    $path = TemporaryProject::composer(manifest: '{"name": "acme/site", "require": {"Hyde/Framework": "^2.0"}}');

    expect((new ProjectDetector())->detect($path)->type)->toBe(ProjectType::Composer);
});

it('refuses a composer project that does not declare Hyde', function () {
    $path = TemporaryProject::directory();

    TemporaryProject::write($path, [
        'composer.json' => json_encode([
            'name' => 'laravel/laravel',
            'require' => [
                'laravel/framework' => '^13.0',
            ],
        ]),
    ]);

    expect(fn () => (new ProjectDetector())->detect($path))
        ->toThrow(
            LauncherException::class,
            'This is a Composer project, but it does not declare Hyde.'
        );
});

/*
|--------------------------------------------------------------------------
| Manifests that mention Hyde without depending on it
|--------------------------------------------------------------------------
*/

it('stays portable when an unrelated manifest sits beside the content', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "# Hello World\n",
        'composer.json' => '{"name": "acme/unrelated", "require": {"monolog/monolog": "^3.0"}}',
    ]);

    expect((new ProjectDetector())->detect($path)->type)->toBe(ProjectType::Portable);
});

it('ignores Hyde mentioned only in the description', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "# Hello World\n",
        'composer.json' => '{"name": "acme/unrelated", "description": "A theme for hyde/framework and hyde/hyde"}',
    ]);

    expect((new ProjectDetector())->detect($path)->type)->toBe(ProjectType::Portable);
});

it('ignores Hyde mentioned only in scripts, extra, or keywords', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "# Hello World\n",
        'composer.json' => json_encode([
            'name' => 'acme/unrelated',
            'keywords' => ['hyde/framework'],
            'scripts' => ['build' => 'hyde build', 'install-hyde' => 'composer require hyde/framework'],
            'extra' => ['laravel' => ['providers' => ['Hyde\\Framework\\HydeServiceProvider']]],
        ]),
    ]);

    expect((new ProjectDetector())->detect($path)->type)->toBe(ProjectType::Portable);
});

it('ignores a satellite package that does not make a project a Hyde project', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "# Hello World\n",
        'composer.json' => '{"name": "acme/unrelated", "require": {"hyde/realtime-compiler": "^4.0"}}',
    ]);

    expect((new ProjectDetector())->detect($path)->type)->toBe(ProjectType::Portable);
});

/*
|--------------------------------------------------------------------------
| Broken manifests
|--------------------------------------------------------------------------
*/

it('refuses to guess when the manifest cannot be parsed', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "# Hello World\n",
        'composer.json' => '{"name": "acme/site", "require": {',
    ]);

    expect(fn () => (new ProjectDetector())->detect($path))
        ->toThrow(LauncherException::class, 'could not be parsed');
});

it('reports a composer project with no vendor directory as a composer project', function () {
    $path = TemporaryProject::composer(withAutoloader: false);

    $project = (new ProjectDetector())->detect($path);

    expect($project->type)->toBe(ProjectType::Composer)
        ->and($project->hasAutoloader())->toBeFalse();
});

it('reports a composer project whose autoloader was deleted as having no autoloader', function () {
    $path = TemporaryProject::composer(withAutoloader: false);

    TemporaryProject::write($path, ['vendor/composer/installed.json' => '{}']);

    $project = (new ProjectDetector())->detect($path);

    expect($project->type)->toBe(ProjectType::Composer)
        ->and($project->hasAutoloader())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Walking the directory tree
|--------------------------------------------------------------------------
*/

it('finds the composer project root from a subdirectory', function () {
    $path = TemporaryProject::composer(['docs/guides/.gitkeep' => '']);

    $project = (new ProjectDetector())->detect($path.'/docs/guides');

    expect($project->type)->toBe(ProjectType::Composer)
        ->and($project->root)->toBe($path)
        ->and($project->workingDirectory)->toBe($path.'/docs/guides');
});

it('does not walk past an unrelated composer project', function () {
    $path = TemporaryProject::directory();

    TemporaryProject::write($path, [
        'composer.json' => '{"name":"laravel/laravel","require":{"laravel/framework":"^13.0"}}',
        'app/.gitkeep' => '',
    ]);

    expect(fn () => (new ProjectDetector())->detect($path.'/app'))
        ->toThrow(
            LauncherException::class,
            'This is a Composer project, but it does not declare Hyde.'
        );
});

it('does not attribute a nested portable site to an enclosing composer project', function () {
    $path = TemporaryProject::composer();

    TemporaryProject::write($path, ['blog/_pages/index.md' => "# Nested\n"]);

    expect((new ProjectDetector())->detect($path.'/blog'))
        ->type->toBe(ProjectType::Portable)
        ->root->toBe($path.'/blog');
});

it('resolves a symlinked project root to its real path', function () {
    $path = TemporaryProject::portable();
    $link = TemporaryProject::directory().'/link';

    symlink($path, $link);

    expect((new ProjectDetector())->detect($link))
        ->type->toBe(ProjectType::Portable)
        ->root->toBe($path);
})->skipOnWindows();

/*
|--------------------------------------------------------------------------
| The Project value object
|--------------------------------------------------------------------------
*/

it('exposes the paths a composer project is dispatched through', function () {
    $path = TemporaryProject::composer(['hyde' => "<?php\n"]);

    $project = (new ProjectDetector())->detect($path);

    expect($project->autoloadPath())->toBe($path.'/vendor/autoload.php')
        ->and($project->entryPoint())->toBe($path.'/hyde')
        ->and($project->hasEntryPoint())->toBeTrue()
        ->and($project->hasAutoloader())->toBeTrue();
});

it('exposes no dependency paths for a portable project', function () {
    $project = Project::portable(TemporaryProject::portable());

    expect($project->autoloadPath())->toBeNull()
        ->and($project->entryPoint())->toBeNull()
        ->and($project->hasAutoloader())->toBeFalse();
});

// The canonical path representation has its own file: tests/Unit/Launcher/ProjectPathTest.php.
