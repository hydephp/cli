<?php

use Hyde\Foundation\HydeKernel;
use Hyde\Foundation\Application;
use Hyde\Foundation\ConsoleKernel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Contracts\Debug\ExceptionHandler;

beforeEach(function () {
   HydeKernel::setInstance(new HydeKernel());
   $this->app = require __DIR__ . '/../../app/bootstrap.php';
});

test('bootstrapper returns application', function () {
    expect($this->app)->toBeInstanceOf(Application::class);
});

it('has correct base path', function () {
    expect($this->app->basePath())->toBe(realpath(__DIR__ . '/../../'));
});

it('has correct config path', function () {
    expect($this->app->configPath())->toBe(realpath(__DIR__ . '/../../config'));
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
    expect(HydeKernel::getInstance()->path())->toBe(realpath(__DIR__ . '/../../'));
});
