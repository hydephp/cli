<?php

/**
 * @internal Updates the version number in the app/Application.php file.
 *
 * @usage php bin/bump-application-version.php [major|minor|patch] [current-version]
 */
require_once __DIR__.'/lib/application-version.php';

echo "Bumping application version!\n";

$type = $argv[1] ?? 'patch';
$applicationPath = __DIR__.'/../app/Application.php';

$application = file_get_contents($applicationPath);

if ($application === false) {
    throw new RuntimeException("Could not read $applicationPath");
}

$version = $argv[2] ?? read_application_version($application);

echo "Current version:   v$version\n";

$version = bump_application_version($version, $type);

echo "New version:       v$version\n";

$updated = preg_replace_callback(
    "/(final public const APP_VERSION = ')[^']+(';)/",
    fn (array $matches): string => $matches[1].$version.$matches[2],
    $application,
    1,
    $count
);

if ($updated === null || $count !== 1) {
    throw new RuntimeException("Could not update APP_VERSION in $applicationPath");
}

if (file_put_contents($applicationPath, $updated) === false) {
    throw new RuntimeException("Could not write $applicationPath");
}
