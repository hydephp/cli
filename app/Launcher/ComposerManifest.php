<?php

declare(strict_types=1);

namespace App\Launcher;

use function ltrim;
use function is_file;
use function is_array;
use function array_flip;
use function json_decode;
use function array_merge;
use function array_filter;
use function array_intersect_key;
use function array_change_key_case;
use function file_get_contents;

/**
 * A minimal, dependency-free reader for a project's composer.json and composer.lock.
 *
 * This runs before any autoloader is registered, so it deliberately uses nothing
 * but the PHP standard library. It answers exactly two questions: does this
 * manifest declare Hyde, and which framework version does it pin?
 */
final class ComposerManifest
{
    /**
     * The packages whose presence in the dependency graph makes a directory a Hyde Composer project.
     *
     * Only the framework and the project package count. Satellite packages such as
     * `hyde/realtime-compiler` may be required by unrelated projects and are not
     * sufficient evidence on their own.
     *
     * @var list<string>
     */
    public const HYDE_PACKAGES = ['hyde/framework', 'hyde/hyde'];

    /** @param array<string, mixed> $data The decoded composer.json contents. */
    private function __construct(public readonly array $data)
    {
        //
    }

    /**
     * Read and decode a composer.json file.
     *
     * @throws \App\Launcher\LauncherException If the file exists but cannot be parsed.
     */
    public static function read(string $path): self
    {
        $contents = is_file($path) ? @file_get_contents($path) : false;

        if ($contents === false) {
            throw new LauncherException("Unable to read the Composer manifest at $path.");
        }

        $data = json_decode($contents, true);

        if (! is_array($data)) {
            throw new LauncherException(<<<TEXT
            The Composer manifest at $path could not be parsed.

            Hyde cannot determine whether this is a Composer project or a portable one,
            so it will not guess. Fix the JSON syntax, or remove the file if the
            directory is meant to be a portable Hyde site.
            TEXT);
        }

        return new self($data);
    }

    /**
     * Does this manifest actually declare Hyde as a dependency?
     *
     * Only the `require` and `require-dev` graphs are inspected. A mention of Hyde
     * in the description, the keywords, a script, or the `extra` section carries
     * no weight, since any unrelated project may reference Hyde in passing.
     */
    public function declaresHyde(): bool
    {
        return $this->hydeRequirements() !== [];
    }

    /** @return array<string, string> The Hyde packages required by the manifest, mapped to their version constraint. */
    public function hydeRequirements(): array
    {
        $requirements = array_merge($this->requirements('require'), $this->requirements('require-dev'));

        return array_intersect_key($requirements, array_flip(self::HYDE_PACKAGES));
    }

    /** @return array<string, string> */
    private function requirements(string $key): array
    {
        $requirements = $this->data[$key] ?? [];

        if (! is_array($requirements)) {
            return [];
        }

        // Composer treats package names case-insensitively, so we do too.
        return array_change_key_case(array_filter($requirements, 'is_string'), CASE_LOWER);
    }

    /**
     * Resolve the installed framework version from the project's composer.lock.
     *
     * Returns null when there is no lock file, or when it does not contain the framework.
     */
    public static function lockedVersion(string $lockFile, string $package = 'hyde/framework'): ?string
    {
        if (! is_file($lockFile)) {
            return null;
        }

        $data = json_decode((string) @file_get_contents($lockFile), true);

        if (! is_array($data)) {
            return null;
        }

        foreach (array_merge($data['packages'] ?? [], $data['packages-dev'] ?? []) as $entry) {
            if (is_array($entry) && ($entry['name'] ?? null) === $package) {
                return isset($entry['version']) ? ltrim((string) $entry['version'], 'v') : null;
            }
        }

        return null;
    }
}
