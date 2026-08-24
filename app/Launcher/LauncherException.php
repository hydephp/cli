<?php

declare(strict_types=1);

namespace App\Launcher;

use RuntimeException;

/**
 * Thrown for conditions that must abort the CLI before the application boots.
 *
 * Every launcher failure is fatal and non-zero by design: the launcher never
 * degrades a broken Composer project into a Portable one.
 */
class LauncherException extends RuntimeException
{
    public function __construct(string $message, public readonly int $status = 1)
    {
        parent::__construct($message, $status);
    }
}
