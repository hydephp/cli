<?php

declare(strict_types=1);

require_once __DIR__.'/../../bin/lib/manual-version.php';

it('parses plain semver versions', function () {
    expect(parse_version('HydePHP v3.0.0 - (HydePHP v3.0.0)'))
        ->toBe('v3.0.0 (CLI v3.0.0)');
});

it('parses a dev framework version alongside a plain CLI version', function () {
    expect(parse_version('HydePHP v3.0.0 - (HydePHP v3.0.0-dev)'))
        ->toBe('v3.0.0-dev (CLI v3.0.0)');
});

it('parses dev prereleases on both versions', function () {
    expect(parse_version('HydePHP v3.0.0-dev - (HydePHP v3.0.0-dev)'))
        ->toBe('v3.0.0-dev (CLI v3.0.0-dev)');
});

it('parses arbitrary prerelease identifiers on both versions', function () {
    expect(parse_version('HydePHP v3.0.0-beta.1 - (HydePHP v3.1.0-beta.2)'))
        ->toBe('v3.1.0-beta.2 (CLI v3.0.0-beta.1)');
});

it('throws when the version string cannot be parsed', function () {
    parse_version('not a version string');
})->throws(Exception::class, 'Failed to parse version: not a version string');
