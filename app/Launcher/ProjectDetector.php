<?php

declare(strict_types=1);

namespace App\Launcher;

use function is_dir;
use function is_file;
use function dirname;
use function realpath;

/**
 * Decides whether a directory is a Portable project or a Composer project.
 *
 * This class is deliberately free of framework dependencies: it is required by
 * explicit path from the console entry point and runs before any autoloader
 * exists, which is what makes it safe to dispatch a Composer project into
 * its own dependency graph without ever touching the embedded one.
 */
final class ProjectDetector
{
    /**
     * Files and directories that mark a directory as the root of a Hyde site.
     *
     * Their presence stops the upwards search: a directory that is itself a site
     * root must never be attributed to an unrelated Composer project further up
     * the tree (a portable site checked out inside a PHP repository, say).
     *
     * @var list<string>
     */
    private const PORTABLE_MARKERS = ['_pages', '_posts', '_docs', '_media', 'hyde.yml', 'hyde.yaml'];

    /** A hard stop for the upwards search, so a pathological path can never spin. */
    private const MAX_DEPTH = 64;

    /**
     * Detect the project rooted at, or containing, the given directory.
     *
     * The search walks upwards from the given directory. For each directory:
     *
     *  1. A composer.json that actually declares Hyde makes it a Composer project root.
     *  2. Otherwise, portable markers make it a Portable project root, and stop the search.
     *  3. Otherwise, we continue with the parent directory.
     *
     * When nothing matches all the way up, the starting directory is a Portable project.
     * Portable is always the fallback, and never a fallback from a *broken* Composer project.
     *
     * @throws \App\Launcher\LauncherException If a composer.json is present but unparseable.
     */
    public function detect(string $directory): Project
    {
        $start = Project::normalize(realpath($directory) ?: $directory);

        $current = $start;

        for ($depth = 0; $depth < self::MAX_DEPTH; $depth++) {
            if ($this->declaresHyde($current)) {
                return Project::composer($current, $start);
            }

            if ($this->hasPortableMarkers($current)) {
                return new Project(ProjectType::Portable, $current, $start);
            }

            $parent = Project::normalize(dirname($current));

            if ($parent === $current) {
                break; // We reached the filesystem root.
            }

            $current = $parent;
        }

        return Project::portable($start);
    }

    /**
     * Does the given directory contain a composer.json that declares Hyde?
     *
     * @throws \App\Launcher\LauncherException If the manifest exists but cannot be parsed.
     */
    public function declaresHyde(string $directory): bool
    {
        $manifest = $directory.'/composer.json';

        if (! is_file($manifest)) {
            return false;
        }

        return ComposerManifest::read($manifest)->declaresHyde();
    }

    private function hasPortableMarkers(string $directory): bool
    {
        foreach (self::PORTABLE_MARKERS as $marker) {
            $path = $directory.'/'.$marker;

            if (is_dir($path) || is_file($path)) {
                return true;
            }
        }

        return false;
    }
}
