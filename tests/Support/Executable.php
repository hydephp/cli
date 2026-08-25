<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Launcher\Platform;

use function trim;
use function getcwd;
use function fclose;
use function mkdir;
use function is_dir;
use function is_file;
use function sys_get_temp_dir;
use function dirname;
use function implode;
use function proc_open;
use function proc_close;
use function is_resource;
use function array_merge;
use function stream_get_contents;
use function escapeshellarg;

/**
 * Runs the built native executable the way a user would.
 *
 * Every invocation goes through an environment scrubbed of anything that could let a
 * system PHP or Composer slip in, which is what makes these tests evidence for the
 * "no PHP required" guarantee rather than a restatement of it.
 */
final class Executable
{
    /** A search path that exists on every POSIX host and contains no PHP and no Composer. */
    public const CLEAN_PATH = '/usr/bin:/bin';

    private static ?string $home = null;

    /** The path to the artifact for this platform, or null when it has not been built. */
    public static function path(): ?string
    {
        $path = dirname(__DIR__, 2).'/builds/'.Platform::current()->releaseAsset();

        return is_file($path) ? $path : null;
    }

    public static function missingMessage(): string
    {
        return 'The native executable has not been built for this platform. Run `bin/build-native.sh` first.';
    }

    /**
     * Run the executable in the given directory and capture what it did.
     *
     * @param  list<string>  $arguments
     * @param  array<string, string>  $environment Extra environment variables.
     * @return array{status: int, output: string}
     */
    public static function run(array $arguments, ?string $directory = null, array $environment = []): array
    {
        $executable = self::path();

        $command = escapeshellarg((string) $executable).' '.implode(' ', array_map('escapeshellarg', $arguments));

        $descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];

        $process = proc_open($command, $descriptors, $pipes, $directory ?? getcwd(), self::environment($environment));

        if (! is_resource($process)) {
            return ['status' => 1, 'output' => 'Unable to start the executable.'];
        }

        fclose($pipes[0]);

        $output = (string) stream_get_contents($pipes[1]);
        $errors = (string) stream_get_contents($pipes[2]);

        fclose($pipes[1]);
        fclose($pipes[2]);

        return ['status' => proc_close($process), 'output' => trim($output."\n".$errors)];
    }

    /**
     * The environment the executable is given.
     *
     * Nothing is inherited: the PATH contains no PHP and no Composer, and HOME points at a
     * scratch directory so the runtime cache is exercised from empty on a fresh run.
     *
     * @param  array<string, string>  $extra
     * @return array<string, string>
     */
    public static function environment(array $extra = []): array
    {
        return array_merge([
            'PATH' => self::CLEAN_PATH,
            'HOME' => self::home(),
            'HYDE_CACHE_DIR' => self::home().'/.cache',
        ], $extra);
    }

    /**
     * A home directory shared by the whole integration suite.
     *
     * It deliberately outlives individual tests so the embedded runtime is extracted once
     * and then reused, which is the behaviour a real user gets on their second command.
     */
    public static function home(): string
    {
        if (self::$home === null) {
            self::$home = sys_get_temp_dir().'/hyde-cli-tests/integration-home';

            if (! is_dir(self::$home)) {
                mkdir(self::$home, 0755, true);
            }
        }

        return self::$home;
    }

    /** Remove the shared home directory, so the next run starts from an empty cache. */
    public static function forgetHome(): void
    {
        if (self::$home !== null) {
            TemporaryProject::delete(self::$home);
        }

        self::$home = null;
    }
}
