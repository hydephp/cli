<?php

declare(strict_types=1);

namespace App\Foundation;

use Illuminate\Filesystem\Filesystem;

use function file_get_contents;
use function file_exists;
use function filesize;
use function hash_file;
use function is_file;
use function is_string;
use function rtrim;
use function str_replace;

/**
 * Adds the executable's app.css to the Portable project's read-only media view.
 *
 * The file is virtual: normal files always win, and no project directory is changed.
 */
final class PortableIlluminateFilesystem extends Filesystem
{
    public function __construct(
        private readonly string $bundledStylesheet,
        private readonly string $virtualStylesheet,
    )
    {
        // Illuminate's Filesystem has no constructor; keeping this explicit makes the
        // dependency on the bundled resource visible.
    }

    public function exists($path): bool
    {
        return parent::exists($path) || $this->isBundledStylesheet($path);
    }

    public function missing($path): bool
    {
        return ! $this->exists($path);
    }

    public function get($path, $lock = false): string
    {
        if (! parent::exists($path) && $this->isBundledStylesheet($path)) {
            return (string) file_get_contents($this->bundledStylesheet);
        }

        return parent::get($path, $lock);
    }

    public function hash($path, $algorithm = 'md5')
    {
        if (! parent::exists($path) && $this->isBundledStylesheet($path)) {
            return hash_file($algorithm, $this->bundledStylesheet);
        }

        return parent::hash($path, $algorithm);
    }

    public function size($path)
    {
        if (! parent::exists($path) && $this->isBundledStylesheet($path)) {
            return (int) filesize($this->bundledStylesheet);
        }

        return parent::size($path);
    }

    private function isBundledStylesheet($path): bool
    {
        if (! is_string($path) || ! is_file($this->bundledStylesheet)) {
            return false;
        }

        return $this->normalize($path) === $this->normalize($this->virtualStylesheet);
    }

    private function normalize(string $path): string
    {
        return rtrim(str_replace('\\', '/', $path), '/');
    }
}
