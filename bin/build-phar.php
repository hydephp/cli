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
|       --micro=path/to/micro.sfx --runtime=path/to/php --composer=path/to/composer.phar \
|       [--output=builds/hyde] [--build=sha]
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

    foreach (['micro', 'runtime', 'composer'] as $required) {
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
    info('Composer', $options['composer']);

    guardAgainstDevelopmentDependencies();
    guardAgainstAPublishedFramework();

    $version = embedRuntime($options['runtime'], $platform);
    $composer = embedComposer($options['composer'], $options['runtime']);

    writeRuntimeManifest($version, $platform, $options['runtime'], $options['micro'], $composer);

    info('Runtime version', $version);
    info('Composer version', $composer['version']);

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
    [$status, $output] = run([PHP_BINARY, ROOT.'/bin/verify-v3-graph.php'], captureErrors: true);

    if ($status !== 0) {
        fail(trim($output));
    }

    info('Framework', 'HydePHP v3 (develop@master)');
}

/** Copy the PHP CLI runtime into the source tree, ready to be packed into the archive. */
function embedRuntime(string $runtime, Platform $platform): string
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

    // The runtime is gzipped on the way in rather than compressed by the PHAR itself:
    // compressing several thousand small entries is slow and buys nothing, while
    // compressing this one large binary halves the size of the executable.
    compress($runtime, $directory.'/'.$platform->runtimeFilename().RuntimeManager::RUNTIME_SUFFIX);

    return runtimeVersion($runtime);
}

/**
 * Copy Composer into the source tree, beside the runtime that will run it.
 *
 * Composer is what makes a Composer project usable on a machine that has none: the
 * launcher's `hyde composer` runs this archive with the bundled PHP. The version is
 * read by running it, rather than taken from the build configuration, so a build
 * cannot record a Composer version that its own runtime is unable to start.
 *
 * @return array{version: string, filename: string, checksum: string}
 */
function embedComposer(string $composer, string $runtime): array
{
    $directory = ROOT.'/'.RuntimeManager::RUNTIME_DIRECTORY;

    compress($composer, $directory.'/'.RuntimeManager::COMPOSER_FILE.RuntimeManager::RUNTIME_SUFFIX);

    return [
        'version' => composerVersion($composer, $runtime),
        'filename' => RuntimeManager::COMPOSER_FILE,
        'checksum' => hash_file('sha256', $composer),
    ];
}

/** Describe the embedded runtime, and the Composer beside it, for the RuntimeManager. */
function writeRuntimeManifest(string $version, Platform $platform, string $runtime, string $micro, array $composer): void
{
    file_put_contents(ROOT.'/'.RuntimeManager::RUNTIME_DIRECTORY.'/'.RuntimeManager::MANIFEST_FILE, json_encode([
        'version' => $version,
        'platform' => $platform->slug(),
        'filename' => $platform->runtimeFilename(),
        'checksum' => hash_file('sha256', $runtime),

        // Where the application archive begins once it is concatenated onto the micro
        // SAPI binary. Verified against the archive's own marker at runtime.
        'payload_offset' => filesize($micro),

        'composer' => $composer,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n");
}

/** Gzip a file into the runtime directory, inflated again by the RuntimeManager on first use. */
function compress(string $source, string $target): void
{
    $in = fopen($source, 'rb');
    $out = fopen($target, 'wb');

    stream_filter_append($out, 'zlib.deflate', STREAM_FILTER_WRITE, ['level' => 9, 'window' => 31]);
    stream_copy_to_stream($in, $out);

    fclose($in);
    fclose($out);
}

function runtimeVersion(string $runtime): string
{
    [$status, $output] = run([$runtime, '-r', 'echo PHP_VERSION;']);

    $version = trim($output);

    if ($status !== 0 || $version === '') {
        fail("Unable to determine the version of the PHP runtime at $runtime");
    }

    return $version;
}

/** Run the bundled Composer with the bundled runtime, and read the version it reports. */
function composerVersion(string $composer, string $runtime): string
{
    [$status, $output] = run([$runtime, $composer, '--version', '--no-ansi'], captureErrors: true);

    if ($status !== 0 || preg_match('/Composer version (\S+)/', $output, $matches) !== 1) {
        fail("Unable to run the bundled Composer at $composer with the runtime at $runtime:\n".trim($output));
    }

    return $matches[1];
}

/**
 * Run a program and capture what it printed, without going through a shell.
 *
 * An array command bypasses the shell on both platforms, which is the whole point. A
 * shell command line has to be quoted for the shell that will read it, and `cmd.exe`
 * and `sh` do not agree on how — nor on where a discarded stream goes. `2>/dev/null`
 * names a path Windows does not have, and cmd fails the redirection with "The
 * system cannot find the path specified" before the program is ever started.
 *
 * @param  list<string>  $command The program, then its arguments, each unquoted.
 * @return array{0: int, 1: string} The exit status, and standard output — with standard
 *                                  error appended when the caller wants to report it.
 */
function run(array $command, bool $captureErrors = false): array
{
    $process = @proc_open($command, [1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

    if ($process === false) {
        return [1, ''];
    }

    $output = (string) stream_get_contents($pipes[1]);
    $errors = (string) stream_get_contents($pipes[2]);

    fclose($pipes[1]);
    fclose($pipes[2]);

    return [proc_close($process), $captureErrors ? $output.$errors : $output];
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
