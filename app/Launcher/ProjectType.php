<?php

declare(strict_types=1);

namespace App\Launcher;

/**
 * The two — and only two — kinds of project the HydeCLI knows how to run.
 *
 * @see \App\Launcher\ProjectDetector for how a directory is mapped onto one of these.
 * @see docs/ARCHITECTURE.md for the full project model.
 */
enum ProjectType: string
{
    /**
     * Content and configuration only. The framework, the runtime, and every
     * dependency is supplied by the Hyde executable. Composer is never
     * invoked, and no vendor directory is created or consulted.
     */
    case Portable = 'portable';

    /**
     * A Composer project that declares Hyde itself. The project owns its
     * dependency graph; the executable only supplies the PHP runtime
     * and hands control over to the project's own entry point.
     */
    case Composer = 'composer';

    /** The human readable label used by `hyde info` and error messages. */
    public function label(): string
    {
        return match ($this) {
            self::Portable => 'Portable',
            self::Composer => 'Composer',
        };
    }
}
