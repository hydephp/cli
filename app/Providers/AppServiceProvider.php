<?php

namespace App\Providers;

use App\Commands\Internal\Describer;
use App\Commands\NewProjectCommand;
use App\Commands\ServeCommand;
use App\Commands\VendorPublishCommand;
use Illuminate\Support\ServiceProvider;
use Humbug\SelfUpdate\Updater as PharUpdater;
use LaravelZero\Framework\Providers\Build\Build;
use LaravelZero\Framework\Components\Updater\Updater;
use NunoMaduro\LaravelConsoleSummary\Contracts\DescriberContract;
use LaravelZero\Framework\Components\Updater\Strategy\GithubStrategy;
use LaravelZero\Framework\Components\Updater\Strategy\StrategyInterface;

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

        $build = $this->app->make(Build::class);

        if ($build->isRunning() && $this->app->environment('production')) {
            $this->app->singleton(Updater::class, function () use ($build) {
                $updater = new PharUpdater($build->getPath(), false, PharUpdater::STRATEGY_GITHUB);

                $composer = json_decode(file_get_contents(__DIR__.'/../../composer.json'), true);
                $name = $composer['name'];

                $strategy = $this->app['config']->get('updater.strategy', GithubStrategy::class);

                $updater->setStrategyObject($this->app->make($strategy));

                if ($updater->getStrategy() instanceof StrategyInterface) {
                    $updater->getStrategy()->setPackageName($name);
                }

                if (method_exists($updater->getStrategy(), 'setCurrentLocalVersion')) {
                    $updater->getStrategy()->setCurrentLocalVersion(config('app.version'));
                }

                return new Updater($updater);
            });
        }
    }
}
