<?php

declare(strict_types=1);

namespace App\Launcher;

use function implode;
use function array_keys;
use function strtolower;
use function php_uname;

/**
 * Maps the host operating system and CPU architecture onto the identifiers
 * used for release artifacts and for the runtime cache directory.
 *
 * Every supported combination is enumerated explicitly; unknown combinations
 * are an error rather than a guess, so a user on an unsupported platform gets
 * a clear message instead of a download that cannot run.
 */
final class Platform
{
    /**
     * The release artifacts published for each supported platform.
     *
     * @var array<string, string>
     */
    private const ASSETS = [
        'linux-x86_64' => 'hyde-linux-x86_64',
        'linux-arm64' => 'hyde-linux-arm64',
        'macos-x86_64' => 'hyde-macos-x86_64',
        'macos-arm64' => 'hyde-macos-arm64',
        'windows-x86_64' => 'hyde-windows-x86_64.exe',
    ];

    public function __construct(
        /** The operating system family, as reported by PHP_OS_FAMILY. */
        public readonly string $os,

        /** The raw machine architecture, as reported by php_uname('m'). */
        public readonly string $architecture,
    ) {
        //
    }

    public static function current(): self
    {
        return new self(PHP_OS_FAMILY, php_uname('m'));
    }

    public function isWindows(): bool
    {
        return $this->os === 'Windows';
    }

    /**
     * The canonical platform slug, such as `macos-arm64`.
     *
     * Used both to name release assets and to namespace the extracted runtime cache,
     * so a cache directory shared over a network mount can never hand a binary
     * for one platform to a machine running another.
     */
    public function slug(): string
    {
        return $this->normalizeOs().'-'.$this->normalizeArchitecture();
    }

    /**
     * The name of the release asset to download for this platform.
     *
     * @throws \App\Launcher\LauncherException If this platform has no published artifact.
     */
    public function releaseAsset(): string
    {
        $slug = $this->slug();

        return self::ASSETS[$slug] ?? throw new LauncherException(
            "There is no HydeCLI release artifact for your platform ($this->os $this->architecture).\n\n".
            'Supported platforms are: '.implode(', ', array_keys(self::ASSETS)).'.'
        );
    }

    /**
     * The name of the detached GPG signature published alongside this platform's artifact.
     */
    public function signatureAsset(): string
    {
        return $this->releaseAsset().'.sig';
    }

    /**
     * The name of the OpenSSL signature published alongside this platform's artifact.
     *
     * This is the signature the self-update command verifies, since OpenSSL is bundled
     * with the executable while GPG may not be present on the user's machine.
     */
    public function opensslSignatureAsset(): string
    {
        return $this->releaseAsset().'.sig.bin';
    }

    public function supported(): bool
    {
        return isset(self::ASSETS[$this->slug()]);
    }

    /** @return array<string, string> */
    public static function assetMap(): array
    {
        return self::ASSETS;
    }

    /** The file name the PHP CLI runtime has on this platform. */
    public function runtimeFilename(): string
    {
        return $this->isWindows() ? 'php.exe' : 'php';
    }

    private function normalizeOs(): string
    {
        return match ($this->os) {
            'Darwin' => 'macos',
            'Linux' => 'linux',
            'Windows' => 'windows',
            default => strtolower($this->os),
        };
    }

    private function normalizeArchitecture(): string
    {
        return match (strtolower($this->architecture)) {
            'x86_64', 'amd64', 'x64' => 'x86_64',
            'arm64', 'aarch64' => 'arm64',
            default => strtolower($this->architecture),
        };
    }
}
