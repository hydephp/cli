<?php

/** @internal Build the documentation manual. */

/** Execute a command in the Hyde CLI and return the output. */
function hyde(string $command): string
{
    return shell_exec("php hyde $command");
}

/** Dump and die with the given data. */
function dd(mixed $data): void
{
    var_dump($data);
    die;
}
