<?php

declare(strict_types=1);

namespace App\Support;

use App\Launcher\RuntimeManager;

use function getenv;
use function is_file;
use function explode;
use function is_string;
use function is_executable;

/**
 * Works out which Composer to run, and how to run it.
 *
 * The executable bundles a Composer of its own, which is the one that gets used:
 * it is the version this release was built and tested against, and it is there on
 * a machine that has no Composer at all. The host's own Composer is the fallback,
 * and is what a source checkout — which bundles nothing — actually uses.
 *
 * The answer is a command rather than a path, because the bundled Composer is a
 * PHAR: it is run by the bundled PHP runtime, not on its own.
 */
final class ComposerBinary
{
    /** The candidate file names, in the order they are tried. */
    private const CANDIDATES = ['composer', 'composer.phar', 'composer.bat', 'composer.exe'];

    /** @internal Test hook allowing the located binary to be forced. */
    public static ?string $fake = null;

    /** @internal Test hook allowing the lookup to be forced to fail. */
    public static bool $forceMissing = false;

    /**
     * The command that runs Composer, or null when there is no Composer to run.
     *
     * @return list<string>|null
     */
    public static function command(?RuntimeManager $runtime = null): ?array
    {
        if (self::$forceMissing) {
            return null;
        }

        if (self::$fake !== null) {
            return [self::$fake];
        }

        $bundled = self::bundled($runtime);

        if ($bundled !== null) {
            return $bundled;
        }

        $host = self::locate();

        return $host === null ? null : [$host];
    }

    /**
     * The command that runs the Composer inside the executable, if it has one.
     *
     * A source checkout has none, and neither does a build made without one.
     *
     * @return list<string>|null
     */
    public static function bundled(?RuntimeManager $runtime = null): ?array
    {
        $runtime ??= RuntimeManager::make();

        return $runtime->hasBundledComposer() ? [$runtime->path(), $runtime->composerPath()] : null;
    }

    /** Find a Composer executable on the host, or null when it has none. */
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

    /** Is there a Composer to run at all, bundled or on the host? */
    public static function available(?RuntimeManager $runtime = null): bool
    {
        return self::command($runtime) !== null;
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
