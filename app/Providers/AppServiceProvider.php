<?php

namespace App\Providers;

use App\Commands\Internal\Describer;
use App\Commands\NewProjectCommand;
use App\Commands\ServeCommand;
use App\Commands\VendorPublishCommand;
use Illuminate\Support\ServiceProvider;
use NunoMaduro\LaravelConsoleSummary\Contracts\DescriberContract;

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
            VendorPublishCommand::class,
        ]);

        // Register custom Laravel summary command describer implementation.
        $this->app->singleton(DescriberContract::class, Describer::class);
    }
}
