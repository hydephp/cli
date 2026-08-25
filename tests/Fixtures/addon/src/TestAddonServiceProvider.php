<?php

declare(strict_types=1);

namespace Hyde\CliTestAddon;

use Illuminate\Support\ServiceProvider;

/**
 * Registers the fixture addon's command through Laravel package discovery.
 *
 * This provider only ever loads from a Composer project's own vendor directory,
 * which is what makes the command a usable probe for dependency isolation.
 */
class TestAddonServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->commands([
            TestAddonCommand::class,
            FailingAddonCommand::class,
        ]);
    }
}
