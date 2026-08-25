<?php

/** @internal Gets the version number from the app/Application.php file. */
require_once __DIR__.'/lib/application-version.php';

$application = file_get_contents(__DIR__.'/../app/Application.php');

if ($application === false) {
    throw new RuntimeException('Could not read app/Application.php');
}

echo read_application_version($application);
