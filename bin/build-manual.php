<?php

/** @internal Build the documentation manual. */

require_once __DIR__ . '/../vendor/autoload.php';

/** Execute a command in the Hyde CLI and return the output. */
function hyde(string $command): string
{
    return shell_exec("php ../hyde $command");
}
