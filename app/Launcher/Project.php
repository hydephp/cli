<?php

declare(strict_types=1);

namespace App\Launcher;

use function rtrim;
use function substr;
use function strtoupper;
use function is_file;
use function realpath;
use function is_string;
use function preg_match;
use function preg_replace;
use function str_replace;
use function str_starts_with;

/**
 * An immutable description of the project the CLI was invoked against.
 *
 * This value object is created by the {@see ProjectDetector} before any
 * autoloader is registered, and is the single source of truth for the
 * project model throughout the rest of the application.
 */
final class Project
{
    public function __construct(
        public readonly ProjectType $type,

        /** The absolute path to the project root (no trailing slash). */
        public readonly string $root,

        /** The directory the user actually invoked the CLI from. */
        public readonly string $workingDirectory,

        /** The path to the project's composer.json, when in Composer mode. */
        public readonly ?string $composerFile = null,
    ) {
        //
    }

    public static function portable(string $directory): self
    {
        $directory = self::normalize($directory);

        return new self(ProjectType::Portable, $directory, $directory);
    }

    public static function composer(string $root, string $workingDirectory): self
    {
        $root = self::normalize($root);

        return new self(ProjectType::Composer, $root, self::normalize($workingDirectory), $root.'/composer.json');
    }

    public function isPortable(): bool
    {
        return $this->type === ProjectType::Portable;
    }

    public function isComposer(): bool
    {
        return $this->type === ProjectType::Composer;
    }

    /** The absolute path to the project's own Composer autoloader, when in Composer mode. */
    public function autoloadPath(): ?string
    {
        return $this->isComposer() ? $this->root.'/vendor/autoload.php' : null;
    }

    /** The absolute path to the project's own console entry point, when in Composer mode. */
    public function entryPoint(): ?string
    {
        return $this->isComposer() ? $this->root.'/hyde' : null;
    }

    public function hasAutoloader(): bool
    {
        return $this->isComposer() && is_file($this->autoloadPath());
    }

    public function hasEntryPoint(): bool
    {
        return $this->isComposer() && is_file($this->entryPoint());
    }

    /**
     * Resolve a path to the canonical spelling of the directory it names.
     *
     * This is the representation every path the launcher exposes is in. Where the path
     * exists on disk it is resolved first, so that a symlink, a relative segment, or a
     * Windows short name (`C:\\Users\\RUNNER~1`) all reduce to the one spelling of the
     * one directory; where it does not exist, the lexical rule alone applies.
     */
    public static function canonicalize(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $real = @realpath($path);

        return self::normalize(is_string($real) && $real !== '' ? $real : $path);
    }

    /**
     * Normalize the spelling of a path, without touching the filesystem.
     *
     * The rule, in order:
     *
     *  1. Backslashes become forward slashes, so one separator is used throughout.
     *  2. Repeated separators collapse, except a leading `//`, which is a UNC share.
     *  3. A leading drive letter is upper-cased, since `c:/` and `C:/` are one drive.
     *  4. A trailing separator is removed, except from a root that consists of one.
     *
     * Dot segments are deliberately left alone: `..` cannot be resolved lexically in the
     * presence of symlinks, so resolving it is {@see canonicalize()}'s job, not this one.
     */
    public static function normalize(string $path): string
    {
        if ($path === '') {
            return '';
        }

        $path = str_replace('\\', '/', $path);

        // A UNC path (`//server/share`) opens with exactly two separators, which are part
        // of the root rather than an empty segment, so they survive the collapse.
        $prefix = str_starts_with($path, '//') ? '/' : '';

        $path = $prefix.preg_replace('#/{2,}#', '/', $path);

        if (preg_match('/^([A-Za-z]):/', $path, $matches) === 1) {
            $path = strtoupper($matches[1]).substr($path, 1);
        }

        $trimmed = rtrim($path, '/');

        // Preserve the POSIX root (/) and bare Windows drive roots (C:/), where the
        // separator is not a separator between segments but the root itself.
        if ($trimmed === '' || preg_match('/^[A-Z]:$/', $trimmed) === 1) {
            return $trimmed.'/';
        }

        return $trimmed;
    }
}
