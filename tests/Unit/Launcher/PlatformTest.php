<?php

declare(strict_types=1);

use App\Launcher\Platform;
use App\Launcher\LauncherException;

/*
|--------------------------------------------------------------------------
| Release asset resolution
|--------------------------------------------------------------------------
|
| Every supported combination of operating system and architecture is asserted
| explicitly. A wrong mapping here would have `hyde self-update` download an
| executable that cannot run on the machine it just replaced.
|
*/

it('resolves the release asset for every supported platform', function (string $os, string $architecture, string $asset) {
    expect((new Platform($os, $architecture))->releaseAsset())->toBe($asset);
})->with([
    'Linux x86_64' => ['Linux', 'x86_64', 'hyde-linux-x86_64'],
    'Linux amd64' => ['Linux', 'amd64', 'hyde-linux-x86_64'],
    'Linux arm64' => ['Linux', 'arm64', 'hyde-linux-arm64'],
    'Linux aarch64' => ['Linux', 'aarch64', 'hyde-linux-arm64'],
    'Darwin arm64' => ['Darwin', 'arm64', 'hyde-macos-arm64'],
    'Darwin x86_64' => ['Darwin', 'x86_64', 'hyde-macos-x86_64'],
    'Windows AMD64' => ['Windows', 'AMD64', 'hyde-windows-x86_64.exe'],
    'Windows x86_64' => ['Windows', 'x86_64', 'hyde-windows-x86_64.exe'],
]);

it('resolves the platform slug for every supported platform', function (string $os, string $architecture, string $slug) {
    expect((new Platform($os, $architecture))->slug())->toBe($slug);
})->with([
    ['Linux', 'x86_64', 'linux-x86_64'],
    ['Linux', 'aarch64', 'linux-arm64'],
    ['Darwin', 'arm64', 'macos-arm64'],
    ['Darwin', 'x86_64', 'macos-x86_64'],
    ['Windows', 'AMD64', 'windows-x86_64'],
]);

it('publishes a signature for every release asset', function (string $os, string $architecture, string $asset) {
    $platform = new Platform($os, $architecture);

    expect($platform->signatureAsset())->toBe($asset.'.sig')
        ->and($platform->opensslSignatureAsset())->toBe($asset.'.sig.bin');
})->with([
    ['Linux', 'x86_64', 'hyde-linux-x86_64'],
    ['Linux', 'aarch64', 'hyde-linux-arm64'],
    ['Darwin', 'arm64', 'hyde-macos-arm64'],
    ['Darwin', 'x86_64', 'hyde-macos-x86_64'],
    ['Windows', 'AMD64', 'hyde-windows-x86_64.exe'],
]);

it('covers every published asset with a mapping', function () {
    expect(Platform::assetMap())->toBe([
        'linux-x86_64' => 'hyde-linux-x86_64',
        'linux-arm64' => 'hyde-linux-arm64',
        'macos-x86_64' => 'hyde-macos-x86_64',
        'macos-arm64' => 'hyde-macos-arm64',
        'windows-x86_64' => 'hyde-windows-x86_64.exe',
    ]);
});

it('refuses to guess an asset for an unsupported platform', function (string $os, string $architecture) {
    expect(fn () => (new Platform($os, $architecture))->releaseAsset())
        ->toThrow(LauncherException::class, 'no HydeCLI release artifact for your platform');
})->with([
    'Linux on 32-bit ARM' => ['Linux', 'armv7l'],
    'Windows on ARM' => ['Windows', 'ARM64'],
    'FreeBSD' => ['BSD', 'x86_64'],
    'Solaris' => ['Solaris', 'x86_64'],
]);

it('knows whether a platform is supported', function () {
    expect((new Platform('Linux', 'x86_64'))->supported())->toBeTrue()
        ->and((new Platform('BSD', 'x86_64'))->supported())->toBeFalse();
});

it('names the runtime binary according to the platform', function () {
    expect((new Platform('Windows', 'AMD64'))->runtimeFilename())->toBe('php.exe')
        ->and((new Platform('Linux', 'x86_64'))->runtimeFilename())->toBe('php')
        ->and((new Platform('Darwin', 'arm64'))->runtimeFilename())->toBe('php');
});

it('detects the current platform', function () {
    $platform = Platform::current();

    expect($platform->os)->toBe(PHP_OS_FAMILY)
        ->and($platform->isWindows())->toBe(PHP_OS_FAMILY === 'Windows');
});
