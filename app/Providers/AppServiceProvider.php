<?php

namespace App\Providers;

use App\Commands\ServeCommand;
use App\Commands\NewProjectCommand;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->commands([
            NewProjectCommand::class,
        ]);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // We need to register the ServeCommand command here,
        // since we are overriding the default serve command in
        // when the default Hyde service provider registers.

        $this->commands([
            ServeCommand::class,
        ]);
    }
}
