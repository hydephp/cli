<?php

/** @internal Build the documentation manual. */

require_once __DIR__ . '/../vendor/autoload.php';

chdir(__DIR__ . '/..');

/** Execute a command in the Hyde CLI and return the output. */
function hyde_exec(string $command): string
{
    return shell_exec("php hyde $command");
}

if (! is_dir('docs/manual')) {
    mkdir('docs/manual', recursive: true);
}

$commands = hyde_exec('list --format=json');
$commands = json_decode($commands, true);
