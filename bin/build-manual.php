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

$commands = hyde_exec('list --format=json --no-ansi');
$commands = json_decode($commands, true);

$xml = hyde_exec('list --format=xml --no-ansi');
file_put_contents('docs/manual/manual.xml', $xml);

$md = hyde_exec('list --format=md --no-ansi');
file_put_contents('docs/manual/manual.md', $md);

function task(string $name, callable $task): void {
    $timeStart = microtime(true);
    echo "$name...";

    $task();

    $time = round((microtime(true) - $timeStart) * 1000, 2);
    echo "\r$name ($time ms)\n";
}
