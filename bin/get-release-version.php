<?php

/**  @internal Gets the version number from the app/Application.php file */
$application = file_get_contents(__DIR__.'/../app/Application.php');

echo trim(str_replace(['final public const APP_VERSION', '=', '\'', ';'], '', array_values(
    array_filter(explode("\n", $application), fn ($line) => str_contains($line, 'APP_VERSION'))
)[0]));
