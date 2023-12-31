<?php

// Bootstrap the Phar application

// Define working directory
define('HYDE_WORKING_DIR', getenv('HYDE_WORKING_DIR') ?: getcwd());

// As the Phar archive is readonly, we define a temporary directory
// that Laravel can use to store the compiled views, cache files,
// and config files, all to allow the binary to run anywhere,
define('HYDE_TEMP_DIR', getenv('HYDE_TEMP_DIR') ?: sprintf('%s/hyde/%s', sys_get_temp_dir(),
    md5(sprintf('%s-%s', HYDE_WORKING_DIR, Hyde\Foundation\HydeKernel::VERSION))
));

// Create and set up the temporary directory if it doesn't exist
if (! is_dir(HYDE_TEMP_DIR)) {
    mkdir(HYDE_TEMP_DIR.'/config', recursive: true);
    mkdir(HYDE_TEMP_DIR.'/app/storage/framework/cache', recursive: true);
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

$app = new \App\Application(HYDE_WORKING_DIR);

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
    \Hyde\Foundation\ConsoleKernel::class
);

$app->singleton(
    Illuminate\Contracts\Debug\ExceptionHandler::class,
    Illuminate\Foundation\Exceptions\Handler::class
);

/*
|--------------------------------------------------------------------------
| Bind Phar helpers
|--------------------------------------------------------------------------
|
| Next, we need to bind some important locations into the container so
| that the application can properly run inside the Phar archive.
|
*/

$app->afterBootstrapping(Hyde\Foundation\Internal\LoadConfiguration::class, function () use ($app) {
    // Set the cache path for the compiled views
    $app['config']->set('view.compiled', HYDE_TEMP_DIR.'/views');
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

$hyde = new \Hyde\Foundation\HydeKernel(
    HYDE_WORKING_DIR
);

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
