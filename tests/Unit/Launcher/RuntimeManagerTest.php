<?php

declare(strict_types=1);

use App\Launcher\Platform;
use App\Launcher\RuntimeManager;
use App\Launcher\LauncherException;
use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| Extraction and verification
|--------------------------------------------------------------------------
|
| These tests build a miniature "application root" containing a fake runtime,
| so the extraction, checksum verification, reuse and repair paths can be
| exercised without a 25 MB binary or a native build.
|
*/

/**
 * @return array{0: RuntimeManager, 1: string, 2: string} The manager, its application root, and the cache root.
 */
function runtimeFixture(string $contents = "#!/bin/sh\necho runtime\n", ?string $checksum = null, ?Platform $platform = null): array
{
    $root = TemporaryProject::directory('runtime-root');
    $cache = TemporaryProject::directory('runtime-cache');

    $platform ??= new Platform('Linux', 'x86_64');

    mkdir($root.'/'.RuntimeManager::RUNTIME_DIRECTORY);

    $binary = $root.'/'.RuntimeManager::RUNTIME_DIRECTORY.'/'.$platform->runtimeFilename().RuntimeManager::RUNTIME_SUFFIX;

    file_put_contents($binary, gzencode($contents));

    file_put_contents($root.'/'.RuntimeManager::RUNTIME_DIRECTORY.'/'.RuntimeManager::MANIFEST_FILE, json_encode([
        'version' => '8.4.24',
        'platform' => $platform->slug(),
        'filename' => $platform->runtimeFilename(),
        'checksum' => $checksum ?? hash('sha256', $contents),
        'payload_offset' => 1024,
    ]));

    putenv("HYDE_CACHE_DIR=$cache");

    return [new RuntimeManager($platform, $root), $root, $cache];
}

afterEach(function () {
    // These tests move the cache root around; leaving any of them set would send a later
    // test looking for a runtime in a directory that does not belong to it.
    foreach (['HYDE_CACHE_DIR', 'XDG_CACHE_HOME', 'HOME', 'LOCALAPPDATA'] as $variable) {
        putenv($variable);
    }

    putenv('HOME='.Tests\TestCase::originalHome());
});

it('extracts the embedded runtime into a versioned cache directory', function () {
    [$manager, , $cache] = runtimeFixture();

    $path = $manager->extract();

    expect($path)->toBe($cache.'/hyde/runtime/8.4.24/linux-x86_64/php')
        ->and(file_get_contents($path))->toBe("#!/bin/sh\necho runtime\n");
});

it('extracts a runtime built for a platform that is not this one', function () {
    // Whether a file can be marked executable is a property of the host filesystem, not of
    // the platform the runtime inside the executable was compiled for. Confusing the two
    // makes every extraction fail on Windows, where no file without an executable
    // extension is ever `is_executable()`, whatever was just chmod-ed.
    [$manager, , $cache] = runtimeFixture(platform: new Platform('Windows', 'AMD64'));

    expect($manager->extract())->toBe($cache.'/hyde/runtime/8.4.24/windows-x86_64/php.exe');
});

it('makes the extracted runtime executable', function () {
    [$manager] = runtimeFixture();

    expect(is_executable($manager->extract()))->toBeTrue();
})->skipOnWindows();

it('reuses an extraction that is already present and valid', function () {
    [$manager] = runtimeFixture();

    $first = $manager->extract();

    touch($first, $stamp = time() - 3600);
    clearstatcache();

    expect($manager->extract())->toBe($first)
        ->and(filemtime($first))->toBe($stamp);
});

it('replaces a corrupted extraction', function () {
    [$manager] = runtimeFixture();

    $path = $manager->extract();

    file_put_contents($path, 'this is not the runtime');

    expect(file_get_contents($manager->extract()))->toBe("#!/bin/sh\necho runtime\n");
});

it('replaces a truncated extraction', function () {
    [$manager] = runtimeFixture();

    $path = $manager->extract();

    file_put_contents($path, substr(file_get_contents($path), 0, 4));

    expect(file_get_contents($manager->extract()))->toBe("#!/bin/sh\necho runtime\n");
});

it('restores an extraction that was deleted', function () {
    [$manager] = runtimeFixture();

    unlink($manager->extract());

    expect(file_exists($manager->extract()))->toBeTrue();
});

it('refuses an embedded runtime whose checksum does not match', function () {
    [$manager] = runtimeFixture(checksum: str_repeat('0', 64));

    expect(fn () => $manager->extract())
        ->toThrow(LauncherException::class, 'failed its checksum verification');
});

it('refuses to write into a read-only cache directory', function () {
    [$manager, , $cache] = runtimeFixture();

    $directory = $cache.'/hyde/runtime/8.4.24/linux-x86_64';

    mkdir($directory, 0755, true);
    chmod($directory, 0555);

    try {
        expect(fn () => $manager->extract())->toThrow(LauncherException::class, 'not writable');
    } finally {
        chmod($directory, 0755);
    }
})->skipOnWindows()->skip(fn () => posix_geteuid() === 0, 'Root ignores directory permissions.');

it('rejects a manifest that is missing or malformed', function () {
    [$manager, $root] = runtimeFixture();

    file_put_contents($root.'/'.RuntimeManager::RUNTIME_DIRECTORY.'/'.RuntimeManager::MANIFEST_FILE, 'not json');

    expect(fn () => $manager->manifest())->toThrow(LauncherException::class, 'missing or malformed');
});

/*
|--------------------------------------------------------------------------
| Locating the application archive
|--------------------------------------------------------------------------
*/

it('accepts a payload offset that points at the archive marker', function () {
    [$manager] = runtimeFixture();

    $executable = TemporaryProject::directory('executable').'/hyde';

    file_put_contents($executable, str_repeat("\0", 512).RuntimeManager::PAYLOAD_PREFIX.RuntimeManager::PAYLOAD_MARKER."\nrest");

    expect($manager->locatePayload($executable, 512))->toBe(512);
});

it('recovers the archive offset when the recorded one is wrong', function () {
    [$manager] = runtimeFixture();

    $executable = TemporaryProject::directory('executable').'/hyde';

    // A longer prefix than the build recorded, as code signing or a resource section would produce.
    file_put_contents($executable, str_repeat("\0", 900).RuntimeManager::PAYLOAD_PREFIX.RuntimeManager::PAYLOAD_MARKER."\nrest");

    expect($manager->locatePayload($executable, 512))->toBe(900);
});

it('refuses an executable that carries no archive at all', function () {
    [$manager] = runtimeFixture();

    $executable = TemporaryProject::directory('executable').'/hyde';

    file_put_contents($executable, str_repeat("\0", 4096));

    expect(fn () => $manager->locatePayload($executable, 512))
        ->toThrow(LauncherException::class, 'could not be located');
});

/*
|--------------------------------------------------------------------------
| Choosing a runtime
|--------------------------------------------------------------------------
*/

it('never resolves a PHP binary from the PATH', function () {
    [$manager] = runtimeFixture();

    // The only interpreter the manager may return besides the embedded one is the very
    // process that is running, which is how it behaves in a source checkout.
    expect($manager->path())->not->toBe('php')
        ->and($manager->path())->toStartWith(getenv('HYDE_CACHE_DIR'));
});

it('falls back to the running interpreter only when there is no embedded runtime', function () {
    $root = TemporaryProject::directory('empty-root');

    $manager = new RuntimeManager(Platform::current(), $root);

    expect($manager->hasEmbeddedRuntime())->toBeFalse()
        ->and($manager->path())->toBe(PHP_BINARY);
})->skip(PHP_SAPI !== 'cli', 'Only meaningful when running under a CLI SAPI.');

it('reports the embedded runtime version', function () {
    [$manager] = runtimeFixture();

    expect($manager->version())->toBe('8.4.24')
        ->and($manager->hasEmbeddedRuntime())->toBeTrue();
});

it('reports the running version when nothing is embedded', function () {
    $manager = new RuntimeManager(Platform::current(), TemporaryProject::directory('empty-root'));

    expect($manager->version())->toBe(PHP_VERSION);
});

it('knows whether the interpreter is the bundled one', function () {
    expect((new RuntimeManager())->isBundled())->toBe(PHP_SAPI === 'micro');
});

/*
|--------------------------------------------------------------------------
| Cache locations
|--------------------------------------------------------------------------
*/

it('follows the XDG specification for the cache root', function () {
    putenv('HYDE_CACHE_DIR');
    putenv('XDG_CACHE_HOME=/xdg/cache');

    try {
        expect((new RuntimeManager(new Platform('Linux', 'x86_64')))->cacheRoot())->toBe('/xdg/cache');
    } finally {
        putenv('XDG_CACHE_HOME');
    }
});

it('falls back to the home cache directory', function () {
    putenv('HYDE_CACHE_DIR');
    putenv('XDG_CACHE_HOME');
    putenv('HOME=/home/emma');

    expect((new RuntimeManager(new Platform('Linux', 'x86_64')))->cacheRoot())->toBe('/home/emma/.cache');
});

it('uses the local application data directory on Windows', function () {
    putenv('HYDE_CACHE_DIR');
    putenv('LOCALAPPDATA=C:\\Users\\emma\\AppData\\Local');

    try {
        expect((new RuntimeManager(new Platform('Windows', 'AMD64')))->cacheRoot())->toBe('C:/Users/emma/AppData/Local');
    } finally {
        putenv('LOCALAPPDATA');
    }
});

it('namespaces the cache by runtime version and platform', function () {
    $manager = new RuntimeManager(new Platform('Darwin', 'arm64'));

    putenv('HYDE_CACHE_DIR=/tmp/cache');

    expect($manager->cacheDirectory(['version' => '8.4.24', 'platform' => 'macos-arm64', 'checksum' => '', 'filename' => 'php']))
        ->toBe('/tmp/cache/hyde/runtime/8.4.24/macos-arm64');
});
