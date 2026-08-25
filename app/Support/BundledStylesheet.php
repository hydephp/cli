<?php

declare(strict_types=1);

namespace App\Support;

use App\Launcher\RuntimeManager;
use RuntimeException;

use function dirname;
use function file_exists;

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
}
