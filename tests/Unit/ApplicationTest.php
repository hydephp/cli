<?php

declare(strict_types=1);

use App\Application;
use Hyde\Foundation\Application as HydeApplication;
use Tests\Support\TemporaryProject;

test('application version constant follows semantic versioning', function () {
    expect(Application::APP_VERSION)->toMatch('/^\d+\.\d+\.\d+$/');
});

test('the CLI is versioned independently of the framework', function () {
    expect(Application::APP_VERSION)->toBe('3.0.0');
});

test('custom application extends Hyde application', function () {
    expect(new Application())->toBeInstanceOf(HydeApplication::class);
});

it('writes its caches outside the project being built', function () {
    $project = TemporaryProject::portable();
    $storage = TemporaryProject::directory('storage');

    $application = new Application($project);

    $application->useStoragePath($storage);

    expect($application->getCachedPackagesPath())->toStartWith($storage)
        ->and($application->getCachedConfigPath())->toStartWith($storage)
        ->and($application->getCachedPackagesPath())->not->toStartWith($project)
        ->and($application->getCachedConfigPath())->not->toStartWith($project);
});

it('falls back to a namespace when the project has no composer manifest', function () {
    $application = new Application();

    $application->setBasePath(TemporaryProject::portable());

    expect($application->getNamespace())->toBe('App');
});

it('returns default command name', function () {
    expect((new Application())->getName())->toBe('list');
});
