<?php

use Hyde\Foundation\Application;
use App\Commands\PharServeCommand;
use App\Providers\AppServiceProvider;
use Illuminate\Console\Application as Artisan;

it('registers commands', function () {
    $app = new Application();

    $app->register(AppServiceProvider::class);
    $app->boot();

    Artisan::starting(function (Artisan $artisan) {
        expect($artisan->all())->toHaveKey('serve')
            ->and($artisan->all()['serve'])
            ->toBeInstanceOf(PharServeCommand::class);
    });

    new Artisan($app, $app->make('events'), '1.0.0');
});
