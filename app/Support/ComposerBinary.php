<?php

declare(strict_types=1);

namespace App\Support;

use function getenv;
use function is_file;
use function explode;
use function is_string;
use function is_executable;

/**
 * Locates a Composer executable on the host.
 *
 * The CLI never needs Composer for itself; this exists only so that
 * `hyde new --composer` can fail cleanly, and before it writes anything,
 * on a machine where Composer is not installed.
 */
final class ComposerBinary
{
    /** The candidate file names, in the order they are tried. */
    private const CANDIDATES = ['composer', 'composer.phar', 'composer.bat', 'composer.exe'];

    /** @internal Test hook allowing the located binary to be forced. */
    public static ?string $fake = null;

    /** @internal Test hook allowing the lookup to be forced to fail. */
    public static bool $forceMissing = false;

    /** Find a Composer executable, or null when the host has none. */
    public static function locate(): ?string
    {
        if (self::$forceMissing) {
            return null;
        }

        if (self::$fake !== null) {
            return self::$fake;
        }

        foreach (self::searchPath() as $directory) {
            foreach (self::CANDIDATES as $candidate) {
                $path = $directory.DIRECTORY_SEPARATOR.$candidate;

                if (is_file($path) && (PHP_OS_FAMILY === 'Windows' || is_executable($path))) {
                    return $path;
                }
            }
        }

        return null;
    }

    public static function available(): bool
    {
        return self::locate() !== null;
    }

    /** @return list<string> */
    private static function searchPath(): array
    {
        $path = getenv('PATH');

        if (! is_string($path) || $path === '') {
            return [];
        }

        return explode(PATH_SEPARATOR, $path);
    }
}
