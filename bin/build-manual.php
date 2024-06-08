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

$commands = task('Getting command list', 'Got command list', function (): array {
    $commands = hyde_exec('list --format=json --no-ansi');
    $commands = json_decode($commands, true);

    return $commands;
});

task('Building XML manual', 'Built XML manual', function (): void {
    $xml = hyde_exec('list --format=xml --no-ansi');
    file_put_contents('docs/manual/manual.xml', $xml);
});

task('Building Markdown manual', 'Built Markdown manual', function (): void {
    $md = hyde_exec('list --format=md --no-ansi');
    file_put_contents('docs/manual/manual.md', $md);
});

function task(string $start, string $end, callable $task): mixed {
    $timeStart = microtime(true);
    echo "$start...";

    $output = $task();

    $time = round((microtime(true) - $timeStart) * 1000, 2);
    echo "\r$end ($time ms)\n";

    return $output;
}
