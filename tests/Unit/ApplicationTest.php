<?php

use App\Application;
use Hyde\Foundation\Application as HydeApplication;

test('application version constant follows semantic versioning', function () {
    $version = Application::APP_VERSION;

    expect($version)->toMatch('/^\d+\.\d+\.\d+$/');
});

test('custom application extends Hyde application', function () {
    expect(new Application())->toBeInstanceOf(HydeApplication::class);
});

it('uses custom cached packages path', function () {
    expect((new Application())->getCachedPackagesPath())->toBe(HYDE_TEMP_DIR.'/app/storage/framework/cache/packages.php');
});

it('uses custom cached config path', function () {
    expect((new Application())->getCachedConfigPath())->toEndWith('app/../app/storage/framework/cache/config.php');
});

it('uses custom namespace', function () {
    expect((new Application())->getNamespace())->toBe('App');
});

it('uses parent namespace logic if composer.json exists', function () {
    $application = new ApplicationWithPublicNamespace();

    $application->namespace = 'Example';
    $application->setBasePath(__DIR__.'/../../');

    expect($application->getNamespace())->toBe('Example');
});

class ApplicationWithPublicNamespace extends Application
{
    public $namespace;
}
