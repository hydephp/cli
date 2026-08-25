<?php

declare(strict_types=1);

namespace App\Launcher;

use Phar;

use function copy;
use function mkdir;
use function chmod;
use function is_dir;
use function rename;
use function unlink;
use function is_file;
use function getenv;
use function getmypid;
use function dirname;
use function basename;
use function hash_file;
use function is_string;
use function is_array;
use function array_map;
use function array_values;
use function is_writable;
use function json_decode;
use function hash_equals;
use function is_executable;
use function file_get_contents;
use function sys_get_temp_dir;
use function clearstatcache;
use function max;
use function glob;
use function feof;
use function fread;
use function strpos;
use function strlen;
use function fopen;
use function substr;
use function fclose;
use function fseek;
use function is_int;
use function bin2hex;
use function hash_init;
use function hash_final;
use function random_bytes;
use function hash_update_stream;
use function stream_copy_to_stream;
use function stream_filter_append;
use function sprintf;

/**
 * Provides a real PHP CLI SAPI binary to commands that need one.
 *
 * The `hyde` executable is a micro SAPI: it can run the embedded application,
 * but it is not a general purpose CLI interpreter, so it cannot serve
 * `php -S` and it cannot run a Composer project's own entry point.
 *
 * To keep the "no PHP required" guarantee, a full static PHP CLI binary is
 * shipped inside the executable as a runtime resource. This class extracts it
 * to a versioned per-user cache directory, verifies it against the checksum
 * recorded at build time, reuses it whenever it is already present and valid,
 * and makes sure it is executable.
 *
 * It never resolves `php` from PATH. The only non-embedded interpreter it will
 * ever use is the very CLI process that is already running the code, which
 * only happens when the CLI is run from a source checkout during development.
 *
 * Composer is bundled the same way, and extracted the same way. It is a PHAR
 * rather than a binary, so it is run by the runtime above rather than on its
 * own, and it is cached by its own version rather than by platform.
 */
final class RuntimeManager
{
    /** The directory inside the application root that holds the embedded runtime. */
    public const RUNTIME_DIRECTORY = 'runtime';

    /** The build manifest describing the embedded runtime. */
    public const MANIFEST_FILE = 'runtime.json';

    /**
     * The Composer archive the executable ships beside the runtime.
     *
     * Composer is a PHAR rather than a native binary, so the same bundled PHP runs it.
     * It is here so that `hyde composer` and `hyde new --composer` work on a machine
     * that has neither PHP nor Composer, which is the whole promise of the CLI.
     */
    public const COMPOSER_FILE = 'composer.phar';

    /**
     * The suffix the embedded runtime binary carries inside the archive.
     *
     * The binary is stored gzipped, which roughly halves the size of the executable.
     * The checksum recorded in the manifest is of the *decompressed* binary, so
     * verification covers exactly the bytes that are executed.
     */
    public const RUNTIME_SUFFIX = '.gz';

    /**
     * The exact opening bytes of the application archive's stub.
     *
     * The build writes this verbatim, so finding it in the executable locates the
     * first byte of the archive without relying on any recorded offset.
     */
    public const PAYLOAD_PREFIX = "#!/usr/bin/env php\n<?php // ";

    /**
     * The marker the application archive's stub begins with.
     *
     * The offset recorded at build time is only a hint: it is measured against the
     * micro SAPI binary before the two are concatenated, and anything that later
     * changes the length of that prefix — code signing, stripping, an embedded
     * ini, a Windows resource section — would silently invalidate it. The marker
     * lets the offset be verified, and recovered, at runtime.
     */
    public const PAYLOAD_MARKER = 'HYDE_PHAR_PAYLOAD';

    private ?string $resolved = null;

    private ?string $resolvedArchive = null;

    private ?string $resolvedComposer = null;

    private readonly Platform $platform;

    public function __construct(?Platform $platform = null, private readonly ?string $applicationRoot = null)
    {
        $this->platform = $platform ?? Platform::current();
    }

    public static function make(): self
    {
        return new self();
    }

    /**
     * Get the path to a usable PHP CLI binary, extracting the embedded runtime if needed.
     *
     * @throws \App\Launcher\LauncherException If no CLI runtime can be provided.
     */
    public function path(): string
    {
        return $this->resolved ??= $this->resolve();
    }

    public function hasEmbeddedRuntime(): bool
    {
        return is_file($this->manifestPath()) && is_file($this->embeddedBinaryPath());
    }

    /** @return array{version: string, platform: string, checksum: string, filename: string} */
    public function manifest(): array
    {
        $manifest = json_decode((string) @file_get_contents($this->manifestPath()), true);

        if (! is_array($manifest) || ! isset($manifest['version'], $manifest['checksum'])) {
            throw new LauncherException('The embedded PHP runtime manifest is missing or malformed. This executable is corrupt; please reinstall it.');
        }

        return [
            'version' => (string) $manifest['version'],
            'platform' => (string) ($manifest['platform'] ?? $this->platform->slug()),
            'checksum' => (string) $manifest['checksum'],
            'filename' => (string) ($manifest['filename'] ?? $this->platform->runtimeFilename()),
        ];
    }

    /**
     * The path to a PHAR archive containing the application, readable by a plain PHP CLI.
     *
     * A micro SAPI executable is a native binary with the archive appended to it, which
     * the PHAR extension cannot open. Any child process that needs the application —
     * the realtime compiler server, most notably — therefore reads it from a copy
     * of the archive payload, extracted next to the PHP runtime and verified
     * against the executable on every run.
     */
    public function archivePath(): string
    {
        if ($this->resolvedArchive !== null) {
            return $this->resolvedArchive;
        }

        $running = Phar::running(false);

        if (PHP_SAPI !== 'micro') {
            // A plain PHAR, or a source checkout, can be read as-is.
            return $this->resolvedArchive = ($running ?: $this->applicationRoot());
        }

        return $this->resolvedArchive = $this->extractArchive($running);
    }

    /** @throws \App\Launcher\LauncherException */
    private function extractArchive(string $executable): string
    {
        $offset = $this->locatePayload($executable, $this->payloadOffset());
        $checksum = $this->hashRange($executable, $offset);

        $manifest = $this->manifest();
        $directory = $this->cacheDirectory($manifest);
        $target = sprintf('%s/app-%s.phar', $directory, substr($checksum, 0, 16));

        clearstatcache(true, $target);

        if ($this->isValid($target, $checksum)) {
            return $target;
        }

        $this->ensureDirectoryExists($directory);

        $temporary = sprintf('%s/.app.%s.%s.phar', $directory, (string) getmypid(), bin2hex(random_bytes(6)));

        $this->copyRange($executable, $offset, $temporary);

        if (! $this->isValid($temporary, $checksum)) {
            @unlink($temporary);

            throw new LauncherException('The bundled application archive failed its checksum verification. This executable may be corrupt or truncated; please reinstall it.');
        }

        $this->install($temporary, $target, $checksum);

        $this->pruneStaleArchives($directory, $target);

        return $target;
    }

    /** The byte offset at which the application archive begins inside the executable. */
    public function payloadOffset(): int
    {
        $manifest = json_decode((string) @file_get_contents($this->manifestPath()), true);

        $offset = is_array($manifest) ? ($manifest['payload_offset'] ?? null) : null;

        if (! is_int($offset) || $offset <= 0) {
            throw new LauncherException('The embedded runtime manifest does not record where the application archive begins. This executable is corrupt; please reinstall it.');
        }

        return $offset;
    }

    /**
     * Confirm the recorded offset really is where the archive begins, and find it if it is not.
     *
     * @throws \App\Launcher\LauncherException If the archive cannot be found at all.
     */
    public function locatePayload(string $executable, int $hint): int
    {
        if ($this->markerIsAt($executable, $hint)) {
            return $hint;
        }

        $found = $this->searchForMarker($executable);

        if ($found === null) {
            throw new LauncherException('The application archive could not be located inside this executable. It may be corrupt or truncated; please reinstall it.');
        }

        return $found;
    }

    private function markerIsAt(string $executable, int $offset): bool
    {
        $handle = @fopen($executable, 'rb');

        if ($handle === false) {
            return false;
        }

        $expected = self::PAYLOAD_PREFIX.self::PAYLOAD_MARKER;

        $found = @fseek($handle, $offset) === 0
            && (string) fread($handle, strlen($expected)) === $expected;

        fclose($handle);

        return $found;
    }

    /** Scan the executable for the archive marker, reading in overlapping chunks. */
    private function searchForMarker(string $executable): ?int
    {
        $handle = @fopen($executable, 'rb');

        if ($handle === false) {
            return null;
        }

        $needle = self::PAYLOAD_PREFIX.self::PAYLOAD_MARKER;

        $chunkSize = 1 << 20;
        $overlap = strlen($needle);
        $offset = 0;
        $carry = '';

        while (! feof($handle)) {
            $chunk = (string) fread($handle, $chunkSize);
            $buffer = $carry.$chunk;

            $position = strpos($buffer, $needle);

            if ($position !== false) {
                fclose($handle);

                return max(0, $offset - strlen($carry) + $position);
            }

            $offset += strlen($chunk);
            $carry = substr($buffer, -$overlap);
        }

        fclose($handle);

        return null;
    }

    private function hashRange(string $path, int $offset): string
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false || @fseek($handle, $offset) !== 0) {
            throw new LauncherException("Unable to read the application archive from $path.");
        }

        $context = hash_init('sha256');
        hash_update_stream($context, $handle);
        fclose($handle);

        return hash_final($context);
    }

    private function copyRange(string $path, int $offset, string $destination): void
    {
        $source = @fopen($path, 'rb');
        $target = @fopen($destination, 'wb');

        if ($source === false || $target === false || @fseek($source, $offset) !== 0) {
            if ($source !== false) {
                fclose($source);
            }

            if ($target !== false) {
                fclose($target);
            }

            throw new LauncherException("Unable to extract the application archive to $destination. Check that the directory is writable.");
        }

        stream_copy_to_stream($source, $target);

        fclose($source);
        fclose($target);
    }

    /** Remove archives left behind by previous versions of the executable. */
    private function pruneStaleArchives(string $directory, string $keep): void
    {
        foreach (glob($directory.'/app-*.phar') ?: [] as $archive) {
            if ($archive !== $keep) {
                @unlink($archive);
            }
        }
    }

    /** The version of the PHP runtime this executable ships, or the running version when there is none. */
    public function version(): string
    {
        return $this->hasEmbeddedRuntime() ? $this->manifest()['version'] : PHP_VERSION;
    }

    /** Is the PHP interpreter currently executing this code the one bundled in the executable? */
    public function isBundled(): bool
    {
        return PHP_SAPI === 'micro';
    }

    private function resolve(): string
    {
        if ($this->hasEmbeddedRuntime()) {
            return $this->extract();
        }

        // Running from a source checkout: the interpreter that is already running this
        // code is a real CLI SAPI, so we can reuse it. This branch is unreachable in a
        // released executable, which always carries an embedded runtime.
        if (PHP_SAPI === 'cli' && is_file(PHP_BINARY)) {
            return PHP_BINARY;
        }

        throw new LauncherException(<<<'TEXT'
        This command needs a PHP CLI runtime, but this executable does not contain one.

        The `hyde` executable normally embeds a PHP runtime for exactly this purpose.
        Reinstall the official release for your platform, or run the CLI from a source
        checkout using a PHP CLI binary.
        TEXT);
    }

    /**
     * Extract the embedded runtime into the user cache, or reuse a valid extraction.
     *
     * @throws \App\Launcher\LauncherException
     */
    public function extract(): string
    {
        $manifest = $this->manifest();

        return $this->extractResource(
            $this->embeddedBinaryPath(),
            $this->cacheDirectory($manifest).'/'.$manifest['filename'],
            $manifest['checksum'],
            'PHP runtime',
            executable: true
        );
    }

    /**
     * Get the path to the bundled Composer archive, extracting it if needed.
     *
     * @throws \App\Launcher\LauncherException If the executable ships no Composer.
     */
    public function composerPath(): string
    {
        return $this->resolvedComposer ??= $this->extractComposer();
    }

    public function hasBundledComposer(): bool
    {
        return is_file($this->embeddedComposerPath()) && $this->composerManifest() !== null;
    }

    /**
     * What the build recorded about the bundled Composer, or null when none was bundled.
     *
     * This is deliberately a separate reading of the manifest rather than a wider
     * {@see self::manifest()}: the PHP runtime is what the executable cannot work
     * without, and describing it must not start depending on Composer being there.
     *
     * @return array{version: string, filename: string, checksum: string, patches: list<string>}|null
     */
    public function composerManifest(): ?array
    {
        if (! is_file($this->manifestPath())) {
            return null;
        }

        $manifest = json_decode((string) @file_get_contents($this->manifestPath()), true);

        $composer = is_array($manifest) ? ($manifest['composer'] ?? null) : null;

        if (! is_array($composer) || ! isset($composer['version'], $composer['checksum'])) {
            return null;
        }

        return [
            'version' => (string) $composer['version'],
            'filename' => (string) ($composer['filename'] ?? self::COMPOSER_FILE),
            'checksum' => (string) $composer['checksum'],

            // What this build changed in the published archive. Empty is the goal.
            'patches' => array_values(array_map('strval', is_array($composer['patches'] ?? null) ? $composer['patches'] : [])),
        ];
    }

    /**
     * The patches this executable carries against the Composer release it ships.
     *
     * The bundled Composer is not always the published archive byte for byte: where a
     * Composer bug stops the CLI working on one of its platforms, the build carries the
     * minimum change needed. What was changed is recorded rather than left to be
     * discovered, so `hyde info -v` can say so.
     *
     * @return list<string>
     */
    public function composerPatches(): array
    {
        return $this->composerManifest()['patches'] ?? [];
    }

    /** The version of Composer this executable ships, if it ships one. */
    public function composerVersion(): ?string
    {
        return $this->composerManifest()['version'] ?? null;
    }

    /** @throws \App\Launcher\LauncherException */
    private function extractComposer(): string
    {
        $manifest = $this->composerManifest();

        if ($manifest === null || ! is_file($this->embeddedComposerPath())) {
            throw new LauncherException(<<<'TEXT'
            This executable does not bundle Composer.

            Released executables ship a Composer of their own, so this is either a build
            made without one, or a source checkout. Install Composer, or use an official
            release for your platform.
            TEXT);
        }

        return $this->extractResource(
            $this->embeddedComposerPath(),
            $this->composerCacheDirectory($manifest).'/'.$manifest['filename'],
            $manifest['checksum'],
            'Composer'
        );
    }

    /**
     * Extract one gzipped resource out of the executable, or reuse a valid extraction.
     *
     * The checksum recorded at build time is of the decompressed file, so verification
     * covers exactly the bytes that will be run.
     *
     * @throws \App\Launcher\LauncherException
     */
    private function extractResource(string $source, string $target, string $checksum, string $label, bool $executable = false): string
    {
        $directory = dirname($target);

        clearstatcache(true, $target);

        if ($this->isValid($target, $checksum)) {
            if ($executable) {
                $this->ensureExecutable($target);
            }

            return $target;
        }

        $this->ensureDirectoryExists($directory);

        $temporary = sprintf('%s/.%s.%s.%s', $directory, basename($target), (string) getmypid(), bin2hex(random_bytes(6)));

        $this->decompress($source, $temporary, $label);

        if (! $this->isValid($temporary, $checksum)) {
            @unlink($temporary);

            throw new LauncherException("The bundled $label failed its checksum verification. This executable may be corrupt or truncated; please reinstall it.");
        }

        if ($executable) {
            $this->ensureExecutable($temporary);
        }

        $this->install($temporary, $target, $checksum, $label);

        return $target;
    }

    /**
     * Move the verified temporary file into place.
     *
     * A rename is atomic on POSIX systems, so concurrent extractions cannot observe a
     * partial file. On Windows a rename over an existing file fails, and the target
     * may additionally be locked by another Hyde process that is currently running
     * it, so the stale file is moved aside first and cleaned up opportunistically.
     */
    private function install(string $temporary, string $target, string $checksum, string $label = 'PHP runtime'): void
    {
        if (@rename($temporary, $target)) {
            return;
        }

        clearstatcache(true, $target);

        // Another process may have finished extracting the very same runtime while we
        // were working. If what is on disk is valid, our copy is simply redundant.
        if ($this->isValid($target, $checksum)) {
            @unlink($temporary);

            return;
        }

        $stale = $target.'.stale-'.bin2hex(random_bytes(6));

        if (@rename($target, $stale) && @rename($temporary, $target)) {
            // The stale binary may still be locked by a running process, in which case
            // the deletion fails harmlessly and it is cleaned up on a later run.
            @unlink($stale);

            $this->ensureExecutable($target);

            return;
        }

        @unlink($temporary);

        throw new LauncherException("Unable to install the bundled $label at $target. Another process may be using it, or the directory may be read-only.");
    }

    /**
     * Copy the gzipped runtime out of the archive, inflating it as it is written.
     *
     * @throws \App\Launcher\LauncherException
     */
    private function decompress(string $source, string $destination, string $label = 'PHP runtime'): void
    {
        $in = @fopen($source, 'rb');
        $out = @fopen($destination, 'wb');

        if ($in === false || $out === false) {
            if ($in !== false) {
                fclose($in);
            }

            if ($out !== false) {
                fclose($out);
            }

            throw new LauncherException("Unable to extract the bundled $label to $destination. Check that the directory is writable.");
        }

        // A window of 31 selects gzip framing rather than raw deflate.
        stream_filter_append($out, 'zlib.inflate', STREAM_FILTER_WRITE, ['window' => 31]);

        stream_copy_to_stream($in, $out);

        fclose($in);
        fclose($out);
    }

    private function isValid(string $path, string $checksum): bool
    {
        if (! is_file($path)) {
            return false;
        }

        $actual = @hash_file('sha256', $path);

        return is_string($actual) && hash_equals($checksum, $actual);
    }

    /**
     * Make the extracted runtime executable, where the host has such a thing.
     *
     * The question is about the filesystem the binary was just written to, so it is asked
     * of the *host* rather than of `$this->platform`, which describes the runtime being
     * extracted. The two are the same in a released executable. They differ under test,
     * where a Linux runtime is extracted on whatever machine the suite runs on, and
     * asking the wrong one turns "this host has no executable bit" into a hard
     * failure: no file NTFS holds is `is_executable()` unless it ends in .exe.
     */
    private function ensureExecutable(string $path): void
    {
        if (Platform::current()->isWindows()) {
            return; // Windows has no executable bit; the .exe extension is what matters.
        }

        if (! is_executable($path)) {
            @chmod($path, 0755);

            clearstatcache(true, $path);

            if (! is_executable($path)) {
                throw new LauncherException("The bundled PHP runtime at $path could not be made executable.");
            }
        }
    }

    private function ensureDirectoryExists(string $directory): void
    {
        if (is_dir($directory)) {
            if (! is_writable($directory)) {
                throw new LauncherException("The Hyde runtime cache directory at $directory is not writable.");
            }

            return;
        }

        if (! @mkdir($directory, 0755, true) && ! is_dir($directory)) {
            throw new LauncherException("Unable to create the Hyde runtime cache directory at $directory.");
        }
    }

    /** @param array{version: string, platform: string, checksum: string, filename: string} $manifest */
    public function cacheDirectory(array $manifest): string
    {
        return sprintf('%s/hyde/runtime/%s/%s', $this->cacheRoot(), $manifest['version'], $manifest['platform']);
    }

    /**
     * Where the bundled Composer is extracted to.
     *
     * Keyed by its own version, and not by the platform: a PHAR is the same file
     * everywhere, and the runtime that runs it is chosen separately.
     *
     * @param  array{version: string, filename: string, checksum: string}  $manifest
     */
    public function composerCacheDirectory(array $manifest): string
    {
        return sprintf('%s/hyde/composer/%s', $this->cacheRoot(), $manifest['version']);
    }

    /** The per-user cache root, following platform conventions and the XDG specification. */
    public function cacheRoot(): string
    {
        $override = getenv('HYDE_CACHE_DIR');

        if (is_string($override) && $override !== '') {
            return Project::canonicalize($override);
        }

        // Unlike the executable bit, this asks the platform the runtime was built for. It
        // only chooses a directory, so a fake platform picks a conventional path rather
        // than failing — which is what lets the Windows branch be covered anywhere.
        if ($this->platform->isWindows()) {
            $appData = getenv('LOCALAPPDATA') ?: getenv('APPDATA');

            return Project::canonicalize(is_string($appData) && $appData !== '' ? $appData : sys_get_temp_dir());
        }

        $xdg = getenv('XDG_CACHE_HOME');

        if (is_string($xdg) && $xdg !== '') {
            return Project::canonicalize($xdg);
        }

        $home = getenv('HOME');

        if (is_string($home) && $home !== '') {
            return Project::canonicalize($home).'/.cache';
        }

        return Project::canonicalize(sys_get_temp_dir()).'/hyde-cache';
    }

    public function applicationRoot(): string
    {
        return $this->applicationRoot ?? dirname(__DIR__, 2);
    }

    public function embeddedBinaryPath(): string
    {
        return $this->applicationRoot().'/'.self::RUNTIME_DIRECTORY.'/'.$this->platform->runtimeFilename().self::RUNTIME_SUFFIX;
    }

    public function embeddedComposerPath(): string
    {
        return $this->applicationRoot().'/'.self::RUNTIME_DIRECTORY.'/'.self::COMPOSER_FILE.self::RUNTIME_SUFFIX;
    }

    public function manifestPath(): string
    {
        return $this->applicationRoot().'/'.self::RUNTIME_DIRECTORY.'/'.self::MANIFEST_FILE;
    }
}
