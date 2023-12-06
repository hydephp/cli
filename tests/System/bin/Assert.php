#!/usr/bin/env php
<?php

/**
 * @internal A micro CLI tool to run cross-platform high-level assertions from the command line
 *
 * @example php Assert.php '10 >= 5'
 * @example php Assert.php 'file_exists("composer.json")'
 * @example php Assert.php 'file_exists("composer.json")' 'is_dir("vendor")'
 */

$assertions = array_slice($argv, 1);

if (empty($assertions)) {
    echo '⚠  No assertions provided' . PHP_EOL;
    exit(2);
}

foreach ($assertions as $assertion) {
    if (eval("return $assertion;")) {
        echo "✔  Assertion passed: $assertion" . PHP_EOL;
    } else {
        echo "❌ Assertion failed: $assertion" . PHP_EOL;
        exit(1);
    }
}

exit(0);

// Helper functions

function str_contains_all(string $haystack, array $needles): bool
{
    foreach ($needles as $needle) {
        if (!str_contains($haystack, $needle)) {
            return false;
        }
    }

    return true;
}

function file_exists_and_is_not_empty(string $path): bool
{
    return file_exists($path) && filesize($path) > 0;
}

function file_contains(string $path, string ...$needles): bool
{
    return file_exists_and_is_not_empty($path) && str_contains_all(file_get_contents($path), $needles);
}

function command_outputs(string $command, string ...$expectedOutputs): bool
{
    return str_contains_all(shell_exec(sprintf('hyde %s --no-interaction', $command)), $expectedOutputs);
}
