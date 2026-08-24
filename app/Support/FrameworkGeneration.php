<?php

declare(strict_types=1);

namespace App\Support;

use Hyde\Foundation\HydeKernel;

use function sprintf;
use function class_exists;

/**
 * Describes which generation of the Hyde framework is embedded in the executable.
 *
 * HydePHP v3 is unreleased and its version numbers have deliberately not been bumped, so
 * `HydeKernel::VERSION` reads `2.0.3` on both the released v2 line and the v3 development
 * line. Reporting that number on its own would tell a user running a v3 build that they
 * are running v2, which is the confusion this exists to avoid.
 *
 * The generation is therefore established the same way the build gate establishes it: by
 * looking for something only one of the lines has. Once v3 is tagged and the version is
 * bumped, this collapses back to the version number alone.
 *
 * @see \bin\verify-v3-graph.php
 */
final class FrameworkGeneration
{
    /**
     * A class introduced by v3, and absent from every released v2 version.
     *
     * The composable code block view model arrived with the rendering pipeline that
     * replaced the filepath comment processor, and is not reachable from user code, so
     * it is not something a v2 project could have supplied by other means.
     */
    private const V3_MARKER = 'Hyde\Markdown\Extensions\CodeBlockViewModel';

    /** Is the embedded framework the unreleased v3 development line? */
    public static function isDevelopment(): bool
    {
        return class_exists(self::V3_MARKER);
    }

    /** The framework version, qualified by its generation when the two disagree. */
    public static function describe(): string
    {
        return self::isDevelopment()
            ? sprintf('%s-dev (v3 development line)', HydeKernel::VERSION)
            : HydeKernel::VERSION;
    }
}
