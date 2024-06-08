<?php

/** @internal Build the documentation manual. */

require_once __DIR__ . '/../vendor/autoload.php';

chdir(__DIR__ . '/..');

if (! is_dir('docs/manual')) {
    mkdir('docs/manual', recursive: true);
}

task('getting|got', 'command list', function (&$commands): void {
    $commands = hyde_exec('list --format=json --no-ansi');
    $commands = json_decode($commands, true);
}, $commands);

task('building|built', 'XML manual', function (): void {
    $xml = hyde_exec('list --format=xml --no-ansi');
    file_put_contents('docs/manual/manual.xml', $xml);
});

task('building|built', 'Markdown manual', function (): void {
    $md = hyde_exec('list --format=md --no-ansi');
    file_put_contents('docs/manual/manual.md', $md);
});

task('building|built', 'Html manual', function () use ($commands): void {
    //
});

/** Execute a command in the Hyde CLI and return the output. */
function hyde_exec(string $command): string
{
    return shell_exec("php hyde $command");
}

/** Run a task and output the time it took to complete. */
function task(string $verb, string $subject, callable $task, &$output = null): void {
    [$start, $end] = str_contains($verb, '|')
        ? explode('|', $verb)
        : [$verb, $verb];

    [$start, $end] = [ucfirst($start), ucfirst($end)];

    $timeStart = microtime(true);
    echo "$start $subject...";

    $task($output);

    $time = round((microtime(true) - $timeStart) * 1000, 2);
    echo "\r$end $subject ($time ms)\n";
}
