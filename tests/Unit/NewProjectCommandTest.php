<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Process;
use Illuminate\Contracts\Console\Kernel;

function bootstrap(): Application
{
    $app = require __DIR__ . '/../../bin/bootstrap.php';

    $app->make(Kernel::class)->bootstrap();

    return $app;
}

test('can create new project', function () {
    $app = bootstrap();

    Process::preventStrayProcesses();

    Process::shouldReceive('command')->once()->with('composer create-project hyde/hyde test-project --prefer-dist --ansi')->andReturnSelf();

    Process::shouldReceive('run')->once()->withArgs([null, Mockery::type(Closure::class)])->andReturnSelf();

    $app->make(Kernel::class)->call('new', ['name' => 'test-project']);
});
