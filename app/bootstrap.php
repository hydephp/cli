<?php

/*
|--------------------------------------------------------------------------
| Bootstrap The Embedded Application
|--------------------------------------------------------------------------
|
| This file boots the Hyde application that ships inside the executable.
| It is only ever reached for a Portable project, or for one of the few
| commands that belong to the CLI itself. A Composer project has been
| dispatched into its own dependency graph long before we get here.
|
*/

use App\Launcher\Launcher;
use App\Launcher\RuntimeManager;
use App\Foundation\PortableHydeKernel;
use App\Foundation\PortableIlluminateFilesystem;
use App\Support\BundledStylesheet;

// Deprecation notices raised by dependencies are not actionable for someone running a
// pre-built executable, and printing them would corrupt command output. Warnings and
// errors are still reported, and the framework's exception handler takes over as
// soon as it has bootstrapped.
error_reporting(E_ALL & ~E_DEPRECATED & ~E_USER_DEPRECATED);

// The launcher has normally already detected the project and defined these constants.
// When the application is booted directly (in the test suite, or when embedding it)
// we run exactly the same detection here, so the environment is identical.
$launcher = new Launcher();
$project = $launcher->detect();

$environment = $launcher->defineEnvironment($project, isolated: $project->isComposer() && ! $launcher->isSelfDispatch($project));

/*
|--------------------------------------------------------------------------
| Prepare The Writable Working Directories
|--------------------------------------------------------------------------
|
| The executable and everything inside it is read only, so we point Laravel
| at a temporary directory for the compiled views, cached configuration
| and framework caches. That is what lets the binary run anywhere.
|
*/

foreach ([$environment['temp'].'/config', $environment['temp'].'/app/storage/framework/cache', $environment['working']] as $directory) {
    if (! is_dir($directory)) {
        @mkdir($directory, 0755, recursive: true);
    }
}

/*
|--------------------------------------------------------------------------
| Create The Application
|--------------------------------------------------------------------------
|
| The first thing we will do is create a new Laravel application instance
| which serves as the "glue" for all the components of Laravel, and is
| the IoC container for the system binding all of the various parts.
|
*/

$app = new \App\Application($environment['working']);

$bundledStylesheet = $project->isPortable() ? BundledStylesheet::path() : null;

if ($bundledStylesheet !== null) {
    // Hyde's service provider applies the configured media directory while providers boot.
    // Bind the overlay after that phase so its exact virtual path matches the kernel.
    $app->afterBootstrapping(\Illuminate\Foundation\Bootstrap\BootProviders::class, function () use ($app, $bundledStylesheet): void {
        $mediaDirectory = \Hyde\Hyde::getMediaDirectory();
        $virtualStylesheet = \Hyde\Hyde::path($mediaDirectory.'/app.css');

        $app->singleton(\Illuminate\Filesystem\Filesystem::class, fn (): PortableIlluminateFilesystem => new PortableIlluminateFilesystem($bundledStylesheet, $virtualStylesheet));
    });
}

// Everything the framework writes goes outside the project being built.
$app->useStoragePath($environment['temp'].'/app/storage');

/*
|--------------------------------------------------------------------------
| Bind Important Interfaces
|--------------------------------------------------------------------------
|
| Next, we need to bind some important interfaces into the container so
| we will be able to resolve them when needed. The kernels serve the
| incoming requests to this application from both the web and CLI.
|
*/

$app->singleton(
    Illuminate\Contracts\Console\Kernel::class,
    \App\Foundation\ConsoleKernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    Illuminate\Foundation\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Bind The Detected Project
|--------------------------------------------------------------------------
|
| The project model is a first class concept, so the detected project is
| bound into the container and can be resolved by any command that needs
| to know which kind of project it is running against.
|
*/

$app->instance(\App\Launcher\Project::class, $project);
$app->instance(\App\Launcher\RuntimeManager::class, RuntimeManager::make());

/*
|--------------------------------------------------------------------------
| Bind Executable Helpers
|--------------------------------------------------------------------------
|
| Next, we need to bind some important locations into the container so
| that the application can properly run inside the Phar archive.
|
*/

$app->afterBootstrapping(\App\Foundation\LoadConfiguration::class, function () use ($app, $environment) {
    // Set the cache path for the compiled views
    $app['config']->set('view.compiled', $environment['temp'].'/views');
});

/*
|--------------------------------------------------------------------------
| Set Important Hyde Configurations
|--------------------------------------------------------------------------
|
| Now, we create a new instance of the HydeKernel, which encapsulates
| our Hyde project and provides helpful methods for interacting with it.
| Then, we bind the kernel into the application service container.
|
*/

$hyde = $project->isPortable()
    ? new PortableHydeKernel($environment['working'])
    : new \Hyde\Foundation\HydeKernel($environment['working']);

$app->singleton(
    \Hyde\Foundation\HydeKernel::class, function (): Hyde\Foundation\HydeKernel {
        return \Hyde\Foundation\HydeKernel::getInstance();
    }
);

\Hyde\Foundation\HydeKernel::setInstance($hyde);

/*
|--------------------------------------------------------------------------
| Return The Application
|--------------------------------------------------------------------------
|
| This script returns the application instance. The instance is given to
| the calling script so we can separate the building of the instances
| from the actual running of the application and sending responses.
|
*/

return $app;
