<?php

use App\Commands\ServeCommand;
use App\Providers\AppServiceProvider;
use Hyde\Foundation\Application;
use Illuminate\Console\Application as Artisan;
use Illuminate\Filesystem\Filesystem;

it('registers commands', function () {
    $app = tap(new Application(), function (Application $app) {
        $app->register(AppServiceProvider::class);
        $app->boot();

        // Bind files to the container, as publish command constructor requires it.
        $app->instance('files', new Filesystem());
    });

    Artisan::starting(function (Artisan $artisan) {
        expect($artisan->all())->toHaveKey('serve')
            ->and($artisan->all()['serve'])
            ->toBeInstanceOf(ServeCommand::class);
    });

    new Artisan($app, $app->make('events'), '1.0.0');
});
