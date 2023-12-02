<?php

use Hyde\Foundation\HydeKernel;
use Hyde\Foundation\Application;
use Hyde\Foundation\ConsoleKernel;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Exceptions\Handler;
use Illuminate\Contracts\Debug\ExceptionHandler;

$app = require __DIR__ . '/../../app/bootstrap.php';

beforeAll(function () {
   HydeKernel::setInstance(new HydeKernel());
});

test('bootstrapper returns application', function () use ($app) {
    expect($app)->toBeInstanceOf(Application::class);
});

it('has correct base path', function () use ($app) {
    expect($app->basePath())->toBe(realpath(__DIR__ . '/../../'));
});

it('has correct config path', function () use ($app) {
    expect($app->configPath())->toBe(realpath(__DIR__ . '/../../config'));
});

it('binds console kernel', function () use ($app) {
    expect($app->make(Kernel::class))->toBeInstanceOf(ConsoleKernel::class);
});

it('binds exception handler', function () use ($app) {
    expect($app->make(ExceptionHandler::class))->toBeInstanceOf(Handler::class);
});

it('binds Hyde kernel', function () use ($app) {
    expect($app->make(HydeKernel::class))->toBeInstanceOf(HydeKernel::class);
});

it('binds Hyde kernel as singleton', function () use ($app) {
    expect($app->make(HydeKernel::class))->toBe($app->make(HydeKernel::class));
});

it('sets Hyde kernel instance', function () use ($app) {
    expect(HydeKernel::getInstance())->toBe($app->make(HydeKernel::class));
});

it('sets Hyde kernel path', function () use ($app) {
    expect(HydeKernel::getInstance()->path())->toBe(realpath(__DIR__ . '/../../'));
});
