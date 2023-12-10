<?php

/**
 * @interal Updates the version number in the app/Application.php file
 *
 * @usage php bin/bump-application-version.php [major|minor|patch]
 */

echo "Bumping application version!\n";

// Get type from argument input
$type = $argv[1] ?? 'patch';

// Get the Application class
$application = file_get_contents(__DIR__.'/../app/Application.php');

// Get current version
$version = trim(str_replace(['final public const APP_VERSION', '=', '\'', ';'], '', array_values(
    array_filter(explode("\n", $application), fn ($line) => str_contains($line, 'APP_VERSION'))
)[0]));

echo "Current version:   v$version\n";

// Split version into parts
$parts = explode('.', $version);

// Get the index of the part to increment
$index = match ($type) {
    'major' => 0,
    'minor' => 1,
    'patch' => 2,
    default => throw new Exception(sprintf('Invalid version type %s', $type))
};

// Increment the part
$parts[$index]++;

// Reset all parts after the incremented part
for ($i = $index + 1; $i < count($parts); $i++) {
    $parts[$i] = 0;
}

// Join the parts back together
$version = implode('.', $parts);

echo "New version:       v$version\n";

// Update the version in the Application class
$application = preg_replace('/APP_VERSION = \'(.*)\'/', "APP_VERSION = '$version'", $application);
file_put_contents(__DIR__.'/../app/Application.php', $application);
