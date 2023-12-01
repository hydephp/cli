<?php

/**
 * This file is based on server.php from the realtime-compiler package,
 * but here modified to work from within the standalone Phar archive.
 */

try {
    define('HYDE_START', microtime(true));
    define('BASE_PATH', realpath(getcwd()));
    define('PHAR_PATH', \Phar::running(false));

    // Load the Composer autoloader from the Phar archive
    Phar::loadPhar(PHAR_PATH, 'hyde.phar');

    require_once 'phar://hyde.phar/vendor/autoload.php';

    define('HYDE_BOOTSTRAP_PATH', 'phar://hyde.phar/app/anonymous-bootstrap.php');

    try {
        $app = \Desilva\Microserve\Microserve::boot(\Hyde\RealtimeCompiler\Http\HttpKernel::class);
        $app->handle() // Process the request and create the response
            ->send(); // Send the response to the client
    } catch (Throwable $exception) {
        \Hyde\RealtimeCompiler\Http\ExceptionHandler::handle($exception)->send();
        exit($exception->getCode());
    }
} catch (\Throwable $th) {
    // Auxiliary exception handler
    echo '<h1>Something went really wrong!</h1>';
    echo '<p>An error occurred that the core exception handler failed to process. Here\'s all we know:</p>';
    echo '<h2>Initial exception:</h2><pre>'.print_r($exception, true).'</pre>';
    echo '<h2>Auxiliary exception:</h2><pre>'.print_r($th, true).'</pre>';
}
