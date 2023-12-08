<?php /** @noinspection PhpPossiblePolymorphicInvocationInspection */

use Hyde\Foundation\Application;
use Hyde\Foundation\ConsoleKernel;
use Hyde\Foundation\HydeKernel;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Exceptions\Handler;

beforeEach(function () {
    $this->app = require __DIR__.'/../../app/bootstrap.php';
});

test('anonymous bootstrapper returns application', function () {
    expect($this->app)->toBeInstanceOf(\App\Application::class)
        ->and($this->app)->toBeInstanceOf(Application::class);
});

it('has correct base path', function () {
    expect($this->app->basePath())->toBe('/path/to/working/dir');
});

it('has correct config path', function () {
    expect($this->app->configPath())->toBe('/path/to/working/dir'.DIRECTORY_SEPARATOR.'config');
});

it('binds console kernel', function () {
    expect($this->app->make(Kernel::class))->toBeInstanceOf(ConsoleKernel::class);
});

it('binds exception handler', function () {
    expect($this->app->make(ExceptionHandler::class))->toBeInstanceOf(Handler::class);
});

it('binds Hyde kernel', function () {
    expect($this->app->make(HydeKernel::class))->toBeInstanceOf(HydeKernel::class);
});

it('binds Hyde kernel as singleton', function () {
    expect($this->app->make(HydeKernel::class))->toBe($this->app->make(HydeKernel::class));
});

it('sets Hyde kernel instance', function () {
    expect(HydeKernel::getInstance())->toBe($this->app->make(HydeKernel::class));
});

it('sets Hyde kernel path', function () {
    expect(HydeKernel::getInstance()->path())->toBe('/path/to/working/dir');
});

it('sets the cached packages path', function () {
    expect($this->app->getCachedPackagesPath())->toBe('/path/to/temp/dir/app/storage/framework/cache/packages.php');
});

it('binds the temporary directory config path', function () {
    ($this->app['events']->getListeners('bootstrapping: '.Hyde\Foundation\Internal\LoadConfiguration::class)[0])($this->app, []);

    expect($this->app->configPath())->toBe('/path/to/temp/dir/config');
});

it('sets the cache path for the compiled views', function () {
    $this->app['config'] = new Repository([]);

    ($this->app['events']->getListeners('bootstrapped: '.Hyde\Foundation\Internal\LoadConfiguration::class)[0])($this->app, []);

    expect($this->app['config']->get('view.compiled'))->toBe('/path/to/temp/dir/views');
});
