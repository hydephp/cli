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
