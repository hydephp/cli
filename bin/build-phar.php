#!/usr/bin/env php
<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Build the HydeCLI application archive and native executable
|--------------------------------------------------------------------------
|
| This script is the PHP half of the native build. It packs the application
| into a PHAR, embeds the static PHP CLI runtime inside it as a resource,
| and concatenates the result onto the micro SAPI binary to produce the
| single-file `hyde` executable.
|
| It is driven by bin/build-native.sh, which produces the two inputs using
| static-php-cli. See docs/ARCHITECTURE.md for the whole picture.
|
| Usage:
|   php -d phar.readonly=0 bin/build-phar.php \
|       --micro=path/to/micro.sfx --runtime=path/to/php [--output=builds/hyde] [--build=sha]
|
*/

require_once __DIR__.'/../app/Launcher/LauncherException.php';
require_once __DIR__.'/../app/Launcher/ProjectType.php';
require_once __DIR__.'/../app/Launcher/Project.php';
require_once __DIR__.'/../app/Launcher/Platform.php';
require_once __DIR__.'/../app/Launcher/ComposerManifest.php';
require_once __DIR__.'/../app/Launcher/ProjectDetector.php';
require_once __DIR__.'/../app/Launcher/RuntimeManager.php';

use App\Launcher\Platform;
use App\Launcher\RuntimeManager;

define('ROOT', realpath(__DIR__.'/..'));

/**
 * Paths that never belong in the distributed archive.
 *
 * The list is deliberately short. Predictable behaviour matters far more than a
 * few megabytes here, so the vendor tree is shipped whole rather than pruned
 * by guesswork about which files a dependency loads at runtime.
 */
const EXCLUDED = ['.git/', '.github/', '.idea/', 'bin/', 'builds/', 'tests/', 'app/storage/', 'vendor/bin/'];

/**
 * Path prefixes that never belong in the archive either.
 *
 * The static-php-cli working directory holds several gigabytes of sources and build
 * output, and there may be one per platform, so it is matched by prefix.
 */
const EXCLUDED_PREFIXES = ['.build'];

exit(main($argv));

function main(array $argv): int
{
    if (ini_get('phar.readonly')) {
        fwrite(STDERR, "This script must run with `php -d phar.readonly=0`.\n");

        return 1;
    }

    $options = parseOptions($argv);

    foreach (['micro', 'runtime'] as $required) {
        if (! isset($options[$required]) || ! is_file($options[$required])) {
            fwrite(STDERR, "Missing or unreadable --$required.\n");

            return 1;
        }
    }

    $platform = Platform::current();
    $output = $options['output'] ?? sprintf('%s/builds/hyde-%s%s', ROOT, $platform->slug(), $platform->isWindows() ? '.exe' : '');
    $archive = ROOT.'/builds/hyde.phar';

    info('Platform', $platform->slug());
    info('Micro SAPI', $options['micro'].' ('.filesize($options['micro']).' bytes)');
    info('PHP runtime', $options['runtime']);

    guardAgainstDevelopmentDependencies();
    guardAgainstAPublishedFramework();

    $version = embedRuntime($options['runtime'], $options['micro'], $platform);

    info('Runtime version', $version);

    writeBuildMetadata($options['build'] ?? null);

    buildArchive($archive);

    info('Archive', $archive.' ('.filesize($archive).' bytes)');

    combine($options['micro'], $archive, $output);

    clearstatcache(true, $output);

    info('Executable', $output.' ('.filesize($output).' bytes)');

    return verify($output, $options['micro']);
}

/**
 * Refuse to build a release artifact out of a development dependency tree.
 *
 * The vendor directory is shipped whole, so a `composer install` that still has the test
 * tooling in it would put Pest, PHPUnit and Mockery inside the executable.
 */
function guardAgainstDevelopmentDependencies(): void
{
    foreach (['pestphp', 'phpunit', 'mockery'] as $package) {
        if (is_dir(ROOT.'/vendor/'.$package)) {
            fail("vendor/$package is present: run `composer install --no-dev` before building.");
        }
    }

    info('Dependencies', 'production only');
}

/**
 * Refuse to build an executable around a published Hyde package.
 *
 * v3 is unreleased and untagged, so a resolution that reaches Packagist gets the v2 line
 * while reporting the same `HydeKernel::VERSION` the development line does. Nothing about
 * the resulting executable would look wrong, which is exactly why this is checked here,
 * at the point the artifact is assembled, rather than left to the build script.
 */
function guardAgainstAPublishedFramework(): void
{
    $status = 0;
    $output = [];

    exec(escapeshellarg(PHP_BINARY).' '.escapeshellarg(ROOT.'/bin/verify-v3-graph.php').' 2>&1', $output, $status);

    if ($status !== 0) {
        fail(implode(PHP_EOL, $output));
    }

    info('Framework', 'HydePHP v3 (develop@master)');
}

/** Copy the PHP CLI runtime into the source tree and describe it for the RuntimeManager. */
function embedRuntime(string $runtime, string $micro, Platform $platform): string
{
    $directory = ROOT.'/'.RuntimeManager::RUNTIME_DIRECTORY;

    // Start from an empty directory so a runtime left over from an earlier build,
    // or from another platform, can never be shipped inside the archive.
    foreach (glob($directory.'/*') ?: [] as $stale) {
        unlink($stale);
    }

    if (! is_dir($directory) && ! mkdir($directory, 0755, true)) {
        fail("Unable to create $directory");
    }

    $target = $directory.'/'.$platform->runtimeFilename().RuntimeManager::RUNTIME_SUFFIX;

    // The runtime is gzipped on the way in rather than compressed by the PHAR itself:
    // compressing several thousand small entries is slow and buys nothing, while
    // compressing this one large binary halves the size of the executable.
    $source = fopen($runtime, 'rb');
    $compressed = fopen($target, 'wb');

    stream_filter_append($compressed, 'zlib.deflate', STREAM_FILTER_WRITE, ['level' => 9, 'window' => 31]);
    stream_copy_to_stream($source, $compressed);

    fclose($source);
    fclose($compressed);

    $version = runtimeVersion($runtime);

    file_put_contents($directory.'/'.RuntimeManager::MANIFEST_FILE, json_encode([
        'version' => $version,
        'platform' => $platform->slug(),
        'filename' => $platform->runtimeFilename(),
        'checksum' => hash_file('sha256', $runtime),

        // Where the application archive begins once it is concatenated onto the micro
        // SAPI binary. Verified against the archive's own marker at runtime.
        'payload_offset' => filesize($micro),
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");

    return $version;
}

function runtimeVersion(string $runtime): string
{
    exec(escapeshellarg($runtime).' -r "echo PHP_VERSION;" 2>/dev/null', $output, $status);

    if ($status !== 0 || ! isset($output[0])) {
        fail("Unable to determine the version of the PHP runtime at $runtime");
    }

    return trim($output[0]);
}

/** Record how this executable was built, so `hyde info -v` can report it. */
function writeBuildMetadata(?string $build): void
{
    file_put_contents(ROOT.'/'.RuntimeManager::RUNTIME_DIRECTORY.'/build.json', json_encode([
        'build' => $build,
        'built_at' => gmdate('c'),
    ], JSON_PRETTY_PRINT)."\n");
}

function buildArchive(string $path): void
{
    @unlink($path);

    if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0755, true)) {
        fail('Unable to create the builds directory');
    }

    // No stored alias: the archive is also read by other processes through its absolute
    // path, and a stored alias would collide with the one the stub maps at runtime.
    $phar = new Phar($path);

    $files = [];

    foreach (sources(ROOT) as $file) {
        $files[relative(ROOT, $file)] = $file;
    }

    // Built in one pass rather than file by file: `Phar::addFile()` re-signs the whole
    // archive on every call, which is quadratic once the embedded runtime is in it.
    $phar->buildFromIterator(new ArrayIterator($files));

    $phar->setStub(stub());
    $phar->setSignatureAlgorithm(Phar::SHA256);

    info('Files', (string) count($files));

    // A runaway archive means something that should have been excluded was not, and the
    // executable would ship with it. Fail the build rather than publish it.
    if (count($files) > 20000 || filesize($path) > 128 * 1024 * 1024) {
        fail(sprintf('The archive is implausibly large: %d files, %d bytes. Check the exclusion list.', count($files), filesize($path)));
    }
}

function relative(string $root, string $path): string
{
    return ltrim(str_replace('\\', '/', substr($path, strlen($root))), '/');
}

/** @return Generator<string> */
function sources(string $root): Generator
{
    $directory = new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS);

    $filter = new RecursiveCallbackFilterIterator($directory, function (SplFileInfo $file) use ($root): bool {
        $relative = relative($root, $file->getPathname());

        foreach (EXCLUDED as $excluded) {
            if (str_starts_with($relative.'/', $excluded) || str_starts_with($relative, $excluded)) {
                return false;
            }
        }

        foreach (EXCLUDED_PREFIXES as $prefix) {
            if (str_starts_with($relative, $prefix)) {
                return false;
            }
        }

        return true;
    });

    foreach (new RecursiveIteratorIterator($filter) as $file) {
        if ($file->isFile()) {
            yield $file->getPathname();
        }
    }
}

function stub(): string
{
    $marker = RuntimeManager::PAYLOAD_PREFIX.RuntimeManager::PAYLOAD_MARKER;

    // The stub must begin with exactly this byte sequence: it is how the running
    // executable finds where the archive it carries starts.
    return $marker."\n".<<<'PHP'

    Phar::mapPhar('hyde.phar');

    require 'phar://hyde.phar/hyde';

    __HALT_COMPILER();
    PHP;
}

function combine(string $micro, string $archive, string $output): void
{
    // Written to a temporary file and renamed into place. macOS caches the code signing
    // verdict for a Mach-O binary against its inode, so overwriting an executable in
    // place leaves the kernel killing every subsequent run of it with SIGKILL.
    $temporary = $output.'.building';

    $handle = fopen($temporary, 'wb');

    if ($handle === false) {
        fail("Unable to open $temporary for writing");
    }

    foreach ([$micro, $archive] as $part) {
        $source = fopen($part, 'rb');
        stream_copy_to_stream($source, $handle);
        fclose($source);
    }

    fclose($handle);

    chmod($temporary, 0755);

    @unlink($output);

    if (! rename($temporary, $output)) {
        fail("Unable to move $temporary to $output");
    }
}

/** Prove the built executable runs, and that the recorded payload offset is right. */
function verify(string $output, string $micro): int
{
    $offset = filesize($micro);
    $expected = RuntimeManager::PAYLOAD_PREFIX.RuntimeManager::PAYLOAD_MARKER;

    $handle = fopen($output, 'rb');
    fseek($handle, $offset);
    $actual = fread($handle, strlen($expected));
    fclose($handle);

    if ($actual !== $expected) {
        fwrite(STDERR, "The recorded payload offset does not point at the archive stub.\n");

        return 1;
    }

    info('Payload offset', (string) $offset.' (verified)');

    return 0;
}

/** @return array<string, string> */
function parseOptions(array $argv): array
{
    $options = [];

    foreach (array_slice($argv, 1) as $argument) {
        if (preg_match('/^--([a-z-]+)(?:=(.*))?$/', $argument, $matches) === 1) {
            $options[$matches[1]] = $matches[2] ?? '';
        }
    }

    return $options;
}

function info(string $label, string $value): void
{
    printf("  %-16s %s\n", $label.':', $value);
}

function fail(string $message): never
{
    fwrite(STDERR, "$message\n");

    exit(1);
}
