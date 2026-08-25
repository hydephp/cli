<?php

declare(strict_types=1);

test('config file has the correct structure', function () {
    $config = require __DIR__.'/../../app/config.php';

    expect($config)->toHaveKeys(['name', 'version', 'env', 'providers', 'aliases']);
});

test('the realtime compiler is registered explicitly rather than discovered', function () {
    // A portable project has no vendor directory for package discovery to read, so the
    // providers the executable ships with have to be listed in its own configuration.
    $config = require __DIR__.'/../../app/config.php';

    expect($config['providers'])->toContain(Hyde\RealtimeCompiler\RealtimeCompilerServiceProvider::class);
});

test('the version string carries no legacy standalone wording', function () {
    $config = require __DIR__.'/../../app/config.php';

    expect($config['version'])
        ->toContain('v'.App\Application::APP_VERSION)
        ->toContain('HydePHP v'.Hyde\Foundation\HydeKernel::VERSION)
        ->not->toContain('Experimental')
        ->not->toContain('Standalone');
});
