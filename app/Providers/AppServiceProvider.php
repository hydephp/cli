<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // We need to register the PharServeCommand command here,
        // since we are overriding the default serve command in
        // when the default Hyde service provider registers.

        $this->commands([
            \App\Commands\PharServeCommand::class,
        ]);
    }
}
