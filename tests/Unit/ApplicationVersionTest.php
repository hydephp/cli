<?php

declare(strict_types=1);

require_once __DIR__.'/../../bin/lib/application-version.php';

it('bumps the CLI version without following the framework version', function (string $type, string $expected): void {
    expect(bump_application_version('0.10.13', $type))->toBe($expected);
})->with([
    ['patch', '0.10.14'],
    ['minor', '0.11.0'],
    ['major', '1.0.0'],
]);

it('rejects malformed versions', function (string $version): void {
    bump_application_version($version, 'patch');
})->with([
    'missing patch' => '0.10',
    'leading zero' => '0.010.13',
    'framework label' => 'v3.0.0',
])->throws(InvalidArgumentException::class, 'Invalid SemVer version');

it('rejects unsupported release types', function (): void {
    bump_application_version('0.10.13', 'release');
})->throws(InvalidArgumentException::class, 'Invalid release type: release');
