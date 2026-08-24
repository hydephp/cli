<?php

/** @internal Parses the `hyde --version` output used by the manual build. */
function parse_version(string $version): string
{
    $semver = '\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?';

    if (! preg_match("/HydePHP v($semver) - \(HydePHP v($semver)\)/", $version, $matches)) {
        throw new Exception("Failed to parse version: $version");
    }

    [, $cliVersion, $hydeVersion] = $matches;

    return "v$hydeVersion (CLI v$cliVersion)";
}
