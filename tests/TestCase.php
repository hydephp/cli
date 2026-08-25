<?php

declare(strict_types=1);

namespace Tests;

use App\Launcher\Launcher;
use PHPUnit\Framework\TestCase as BaseTestCase;

use function getenv;
use function is_dir;
use function mkdir;
use function putenv;
use function is_string;
use function sys_get_temp_dir;

abstract class TestCase extends BaseTestCase
{
    private static ?string $home = null;

    private static ?string $cache = null;

    /** The home directory the suite started with, so tests that move it can put it back. */
    public static function originalHome(): string
    {
        return self::$home ??= (is_string($value = getenv('HOME')) ? $value : sys_get_temp_dir());
    }

    /** A cache root shared by the suite, so no test ever writes to the developer's own cache. */
    public static function cacheDirectory(): string
    {
        if (self::$cache === null) {
            self::$cache = sys_get_temp_dir().'/hyde-cli-tests/cache';

            if (! is_dir(self::$cache)) {
                mkdir(self::$cache, 0755, true);
            }
        }

        return self::$cache;
    }

    protected function setUp(): void
    {
        parent::setUp();

        self::originalHome();

        $this->resetEnvironment();
    }

    protected function tearDown(): void
    {
        $this->resetEnvironment();

        parent::tearDown();
    }

    private function resetEnvironment(): void
    {
        Launcher::swap(null);

        putenv('HYDE_WORKING_DIR');
        putenv('HYDE_TEMP_DIR');
        putenv('XDG_CACHE_HOME');
        putenv('LOCALAPPDATA');
        putenv('HOME='.self::originalHome());
        putenv('HYDE_CACHE_DIR='.self::cacheDirectory());
    }
}
