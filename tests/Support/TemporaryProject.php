<?php

declare(strict_types=1);

namespace Tests\Support;

use FilesystemIterator;
use RecursiveIteratorIterator;
use RecursiveDirectoryIterator;

use function rmdir;
use function mkdir;
use function unlink;
use function is_dir;
use function bin2hex;
use function dirname;
use function realpath;
use function sys_get_temp_dir;
use function random_bytes;
use function file_put_contents;

/**
 * Creates real project directories on disk for tests to run against.
 *
 * Fixtures are built outside the repository on purpose. The CLI's own checkout is a
 * Hyde Composer project, so a fixture placed inside it that has no portable marker
 * of its own would be attributed to the CLI's `composer.json` by the detector's
 * upwards search — which is correct behaviour, and exactly what a fixture must
 * not be subject to.
 */
final class TemporaryProject
{
    /** @var list<string> */
    private static array $created = [];

    /** Create an empty directory that no project detection will ever attribute to this repository. */
    public static function directory(string $prefix = 'project'): string
    {
        $path = sys_get_temp_dir().'/hyde-cli-tests/'.$prefix.'-'.bin2hex(random_bytes(6));

        mkdir($path, 0755, true);

        self::$created[] = $path;

        return realpath($path);
    }

    /**
     * Create a Portable project containing the given files.
     *
     * @param  array<string, string>  $files Relative path => contents.
     */
    public static function portable(array $files = ['_pages/index.md' => "# Hello World\n"]): string
    {
        $path = self::directory('portable');

        self::write($path, $files);

        return $path;
    }

    /**
     * Create a Composer project whose manifest declares Hyde.
     *
     * @param  array<string, string>  $files Extra files, relative path => contents.
     */
    public static function composer(array $files = [], bool $withAutoloader = true, ?string $manifest = null): string
    {
        $path = self::directory('composer');

        self::write($path, [
            'composer.json' => $manifest ?? <<<'JSON'
            {
                "name": "acme/site",
                "require": {
                    "php": "^8.2",
                    "hyde/framework": "^2.0"
                }
            }
            JSON,
            '_pages/index.md' => "# Composer project\n",
        ] + $files);

        if ($withAutoloader) {
            self::write($path, ['vendor/autoload.php' => "<?php\n\nreturn null;\n"]);
        }

        return $path;
    }

    /** @param array<string, string> $files */
    public static function write(string $path, array $files): void
    {
        foreach ($files as $relative => $contents) {
            $file = $path.'/'.$relative;

            if (! is_dir(dirname($file))) {
                mkdir(dirname($file), 0755, true);
            }

            file_put_contents($file, $contents);
        }
    }

    /** Remove every directory created during the current test run. */
    public static function cleanup(): void
    {
        foreach (self::$created as $path) {
            self::delete($path);
        }

        self::$created = [];
    }

    public static function delete(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS),
            RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($files as $file) {
            $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
        }

        rmdir($path);
    }
}
