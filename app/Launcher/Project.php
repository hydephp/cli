<?php

declare(strict_types=1);

namespace App\Launcher;

use function rtrim;
use function is_file;
use function preg_match;
use function str_replace;

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

    /** Normalize a path to use forward slashes without a trailing separator, so paths compare equal across platforms. */
    public static function normalize(string $path): string
    {
        $path = str_replace('\\', '/', $path);

        $trimmed = rtrim($path, '/');

        // Preserve the POSIX root (/) and bare Windows drive roots (C:/) which must keep their trailing slash.
        if ($trimmed === '' || preg_match('/^[A-Za-z]:$/', $trimmed) === 1) {
            return $trimmed.'/';
        }

        return $trimmed;
    }
}
