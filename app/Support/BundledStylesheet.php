<?php

declare(strict_types=1);

namespace App\Support;

use App\Launcher\RuntimeManager;
use RuntimeException;

use function dirname;
use function file_exists;
use function file_get_contents;
use function is_file;
use function parse_url;
use function rtrim;
use function str_replace;
use function trim;

/** Locates the production stylesheet carried by the CLI application. */
final class BundledStylesheet
{
    public static function path(): string
    {
        $candidates = [
            dirname(__DIR__, 2).'/runtime/'.RuntimeManager::STYLESHEET_FILE,
            dirname(__DIR__, 3).'/develop/packages/hyde/_media/app.css',
            dirname(__DIR__, 3).'/develop/_media/app.css',
        ];

        foreach ($candidates as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        throw new RuntimeException('The bundled Hyde app.css stylesheet is missing. Rebuild the CLI or sync the Hyde develop checkout.');
    }

    /** The virtual source path represented by the fallback. */
    public static function sourcePath(string $mediaDirectory): string
    {
        return trim($mediaDirectory, '/\\').'/'.RuntimeManager::STYLESHEET_FILE;
    }

    /** The URL path at which the fallback is published by Hyde. */
    public static function outputPath(string $mediaOutputDirectory): string
    {
        return trim($mediaOutputDirectory, '/\\').'/'.RuntimeManager::STYLESHEET_FILE;
    }

    /**
     * Whether a server request should be answered by the bundled fallback.
     *
     * This is intentionally independent of `_media`: the configured media directory is
     * the source of truth, and an existing source file always remains authoritative.
     */
    public static function servesRequest(
        string $requestUri,
        string $projectRoot,
        string $mediaDirectory,
        string $mediaOutputDirectory,
    ): bool {
        $requestPath = (string) (parse_url($requestUri, PHP_URL_PATH) ?: $requestUri);
        $requestPath = trim(str_replace('\\', '/', $requestPath), '/');

        if ($requestPath !== self::outputPath($mediaOutputDirectory)) {
            return false;
        }

        return ! is_file(rtrim($projectRoot, '/\\').'/'.self::sourcePath($mediaDirectory));
    }

    /** Read the bundled bytes for the virtual stylesheet. */
    public static function contents(?string $path = null): string
    {
        return (string) file_get_contents($path ?: self::path());
    }
}
