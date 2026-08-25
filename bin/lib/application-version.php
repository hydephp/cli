<?php

/** @internal Shared helpers for reading and bumping the HydeCLI version. */

function read_application_version(string $application): string
{
    if (! preg_match("/final public const APP_VERSION = '([^']+)'/", $application, $matches)) {
        throw new RuntimeException('Could not find APP_VERSION in app/Application.php');
    }

    $version = $matches[1];

    if (! is_valid_semver($version)) {
        throw new InvalidArgumentException("Invalid SemVer version: $version");
    }

    return $version;
}

function bump_application_version(string $version, string $type): string
{
    if (! is_valid_semver($version)) {
        throw new InvalidArgumentException("Invalid SemVer version: $version");
    }

    $parts = array_map('intval', explode('.', preg_split('/[-+]/', $version, 2)[0]));

    $index = match ($type) {
        'major' => 0,
        'minor' => 1,
        'patch' => 2,
        default => throw new InvalidArgumentException("Invalid release type: $type"),
    };

    $parts[$index]++;

    for ($i = $index + 1; $i < count($parts); $i++) {
        $parts[$i] = 0;
    }

    return implode('.', $parts);
}

function is_valid_semver(string $version): bool
{
    $identifier = '(?:0|[1-9]\\d*|[0-9A-Za-z-]*[A-Za-z-][0-9A-Za-z-]*)';

    return (bool) preg_match(
        "/\\A(0|[1-9]\\d*)\\.(0|[1-9]\\d*)\\.(0|[1-9]\\d*)(?:-".$identifier."(?:\\.".$identifier.")*)?(?:\\+[0-9A-Za-z-]+(?:\\.[0-9A-Za-z-]+)*)?\\z/",
        $version
    );
}
