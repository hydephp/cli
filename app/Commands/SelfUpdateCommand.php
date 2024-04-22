<?php

/** @noinspection PhpComposerExtensionStubsInspection as we have our own extension check */

declare(strict_types=1);

namespace App\Commands;

use Throwable;
use App\Application;
use RuntimeException;
use Illuminate\Support\Str;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use App\Commands\Internal\ReportsSelfUpdateCommandIssues;

use function exec;
use function fopen;
use function chmod;
use function umask;
use function fclose;
use function rename;
use function filled;
use function explode;
use function ini_set;
use function sprintf;
use function implode;
use function tempnam;
use function dirname;
use function passthru;
use function in_array;
use function array_map;
use function curl_init;
use function curl_exec;
use function curl_close;
use function json_decode;
use function is_writable;
use function curl_setopt;
use function str_contains;
use function array_combine;
use function clearstatcache;
use function escapeshellarg;
use function sys_get_temp_dir;
use function extension_loaded;
use function file_get_contents;
use function get_included_files;

/**
 * @experimental This command is highly experimental and may contain bugs.
 *
 * @internal This command should not be accessed from the code as it may change significantly.
 *
 * @link https://github.com/composer/composer/blob/main/src/Composer/Command/SelfUpdateCommand.php contains some code we can probably use
 */
class SelfUpdateCommand extends Command
{
    use ReportsSelfUpdateCommandIssues;

    /** @var string */
    protected $signature = 'self-update {--check : Check for a new version without updating} {--force-composer-update : Internal temporary flag to force a Composer update}';

    /** @var string */
    protected $description = 'Update the standalone application to the latest version.';

    protected const STATE_BEHIND = 1;
    protected const STATE_UP_TO_DATE = 2;
    protected const STATE_AHEAD = 3;

    protected const STRATEGY_DIRECT = 'direct';
    protected const STRATEGY_COMPOSER = 'composer';

    protected const COMPOSER_COMMAND = 'composer global require hyde/cli';

    /** @var array<string, string|array<string>> The latest release information from the GitHub API */
    protected array $release;

    /**
     * @var string The path to the application executable
     * @example Generally /user/bin/hyde, /usr/local/bin/hyde, /home/<User>/.config/composer/vendor/bin/hyde, or C:\Users\<User>\AppData\Roaming\Composer\vendor\bin\hyde
     */
    protected string $applicationPath;

    public function handle(): int
    {
        try {
            if ($this->output->isVerbose()) {
                $this->info("Checking for a new version...\n");
            } else {
                $this->output->write('<info>Checking for updates...</info> ');
            }

            $this->applicationPath = $this->findApplicationPath();
            $this->debug("Application path: $this->applicationPath");

            $strategy = $this->determineUpdateStrategy();

            /** @deprecated */
            if ($this->option('force-composer-update')) {
                $strategy = self::STRATEGY_COMPOSER;
            }

            $this->debug('Update strategy: '.($strategy === self::STRATEGY_COMPOSER ? 'Composer' : 'Direct download'));

            $currentVersion = $this->parseVersion(Application::APP_VERSION);
            $this->debug('Current version: v'.implode('.', $currentVersion));

            $latestVersion = $this->parseVersion($this->getLatestReleaseVersion());
            $this->debug('Latest version: v'.implode('.', $latestVersion));

            $this->printNewlineIfVerbose();

            $state = $this->compareVersions($currentVersion, $latestVersion);
            $this->printVersionStateInformation($state);

            if ($this->option('check')) {
                return Command::SUCCESS;
            }

            if ($state !== self::STATE_BEHIND && ! $this->option('force-composer-update')) {
                return Command::SUCCESS;
            }

            $this->info('Updating to the latest version...');

            $this->updateApplication($strategy);

            $this->printNewlineIfVerbose();

            $this->info('The application has been updated successfully.');

            // Verify the application version (// Fixme: This shows the old version when using Composer to update {@see https://github.com/hydephp/cli/issues/97})
            passthru('hyde --version --ansi');

            // Now we can exit the application, we do this manually to avoid issues when Laravel tries to clean up the application
            exit(0);
        } catch (Throwable $exception) {
            // Handle known exceptions
            if ($exception instanceof RuntimeException) {
                $known = [
                    'The application path is not writable. Please rerun the command with elevated privileges.',
                    'The application path is not writable. Please rerun the command with elevated privileges (e.g. using sudo).',
                    'The Curl extension is required to use the self-update command.',
                ];

                if (in_array($exception->getMessage(), $known, true)) {
                    $this->output->error($exception->getMessage());
                    return Command::FAILURE;
                }
            }

            // Handle unknown exceptions
            $this->output->error('Something went wrong while updating the application!');

            $this->line(" <error>{$exception->getMessage()}</error> on line <comment>{$exception->getLine()}</comment> in file <comment>{$exception->getFile()}</comment>");

            if (! $this->output->isVerbose()) {
                $this->line(' <fg=gray>For more information, run the command again with the `-v` option to throw the exception.</>');
            }

            $this->newLine();
            $this->warn('As the self-update command is experimental, this may be a bug within the command itself.');

            $this->line(sprintf('<info>%s</info> <href=%s>%s</>', 'Please report this issue on GitHub so we can fix it!',
                $this->createIssueTemplateLink($exception), 'https://github.com/hydephp/cli/issues/new?title=Error+while+self-updating+the+application'
            ));

            if ($this->output->isVerbose()) {
                throw $exception;
            }

            return Command::FAILURE;
        }
    }

    protected function getLatestReleaseVersion(): string
    {
        $this->getLatestReleaseInformation();

        return $this->release['tag_name'];
    }

    protected function getLatestReleaseInformation(): void
    {
        $data = json_decode($this->makeGitHubApiResponse(), true);

        $this->validateReleaseData($data);

        $this->release = $data;
    }

    protected function makeGitHubApiResponse(): string
    {
        // Set the user agent as required by the GitHub API
        ini_set('user_agent', $this->getUserAgent());

        return file_get_contents('https://api.github.com/repos/hydephp/cli/releases/latest');
    }

    protected function getUserAgent(): string
    {
        return sprintf('HydePHP CLI updater v%s (github.com/hydephp/cli)', Application::APP_VERSION);
    }

    protected function validateReleaseData(array $data): void
    {
        $this->assertReleaseEntryIsValid(isset($data['tag_name']));
        $this->assertReleaseEntryIsValid(isset($data['assets']));
        $this->assertReleaseEntryIsValid(isset($data['assets'][0]));
        $this->assertReleaseEntryIsValid(isset($data['assets'][0]['browser_download_url']));
        $this->assertReleaseEntryIsValid(isset($data['assets'][0]['name']) && $data['assets'][0]['name'] === 'hyde');
        $this->assertReleaseEntryIsValid(isset($data['assets'][1]));
        $this->assertReleaseEntryIsValid(isset($data['assets'][1]['browser_download_url']));
        $this->assertReleaseEntryIsValid(isset($data['assets'][1]['name']) && $data['assets'][1]['name'] === 'hyde.sig');
    }

    protected function assertReleaseEntryIsValid(bool $condition): void
    {
        if (! $condition) {
            throw new RuntimeException('Invalid release data received from the GitHub API.');
        }
    }

    /** @return array{major: int, minor: int, patch: int} */
    protected function parseVersion(string $semver): array
    {
        return array_combine(['major', 'minor', 'patch'],
            array_map('intval', explode('.', $semver))
        );
    }

    /** @return self::STATE_* */
    protected function compareVersions(array $currentVersion, array $latestVersion): int
    {
        if ($currentVersion === $latestVersion) {
            return self::STATE_UP_TO_DATE;
        }

        if ($currentVersion < $latestVersion) {
            return self::STATE_BEHIND;
        }

        return self::STATE_AHEAD;
    }

    protected function findApplicationPath(): string
    {
        // Get the full path to the application executable file

        return get_included_files()[0]; // Could also try realpath($_SERVER['argv'][0]) (used by Composer)
    }

    /** @param self::STATE_* $state */
    protected function printVersionStateInformation(int $state): void
    {
        $message = match ($state) {
            self::STATE_BEHIND => 'A new version is available',
            self::STATE_UP_TO_DATE => 'You are already using the latest version',
            self::STATE_AHEAD => 'You are using a development version',
        };

        if ($state === self::STATE_BEHIND) {
            $this->line(sprintf('<info>%s</info> (<comment>%s</comment> <fg=gray>-></> <comment>%s</comment>)', $message, 'v'.Application::APP_VERSION, $this->release['tag_name']));
        } else {
            $this->line(sprintf('<info>%s</info> (<comment>%s</comment>)', $message, $this->release['tag_name']));
        }
    }

    /** @param self::STRATEGY_* $strategy */
    protected function updateApplication(string $strategy): void
    {
        $this->debug('Updating the application...');

        match ($strategy) {
            self::STRATEGY_DIRECT => $this->updateDirectly(),
            self::STRATEGY_COMPOSER => $this->updateViaComposer(),
        };
    }

    /** @return self::STRATEGY_* */
    protected function determineUpdateStrategy(): string
    {
        // Check if the application is installed via Composer
        if (Str::contains($this->applicationPath, 'composer', true)) {
            return self::STRATEGY_COMPOSER;
        }

        return self::STRATEGY_DIRECT;
    }

    protected function updateDirectly(): void
    {
        // Check that the executable path is writable
        if (! is_writable($this->applicationPath)) {
            throw new RuntimeException('The application path is not writable. Please rerun the command with elevated privileges.');
        }

        // Check that the Curl extension is available
        if (! extension_loaded('curl')) {
            throw new RuntimeException('The Curl extension is required to use the self-update command.');
        }

        $this->debug('Downloading the latest version...');

        $tempPath = tempnam(sys_get_temp_dir(), 'hyde');

        // Download the latest release from GitHub
        $phar = $tempPath.'.phar';
        $this->downloadFile($this->release['assets'][0]['browser_download_url'], $phar);
        $signature = $tempPath.'.sig';
        $this->downloadFile($this->release['assets'][1]['browser_download_url'], $signature);

        if (! extension_loaded('openssl')) {
            $this->warn('Skipping signature verification as the OpenSSL extension is not available.');
        } else {
            $this->verifySignature($phar, $signature);
        }

        // Make the downloaded file executable
        chmod($phar, 0755);

        // Replace the current application with the downloaded one
        $this->replaceApplication($phar);
    }

    protected function downloadFile(string $url, string $destination): void
    {
        $this->debug("Downloading $url to $destination");

        $file = fopen($destination, 'wb');
        $ch = curl_init($url);

        curl_setopt($ch, CURLOPT_FILE, $file);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch);

        curl_close($ch);
        fclose($file);
    }

    protected function verifySignature(string $phar, string $signature): void
    {
        // TODO: Implement signature verification
    }

    protected function replaceApplication(string $downloadedFile): void
    {
        $this->debug("Moving file $downloadedFile to $this->applicationPath");

        // Replace the current application with the downloaded one
        try {
            // This might give Permission denied if we can't write to the bin path (might need sudo)
            $this->moveFile($downloadedFile, $this->applicationPath);
        } catch (Throwable $exception) {
            // Check if it is a permission issue
            if (Str::containsAll($exception->getMessage(), ['rename', 'Permission denied'])) {
                throw new RuntimeException('The application path is not writable. Please rerun the command with elevated privileges (e.g. using sudo).', 126, $exception);
            }

            // Unknown error, rethrow the exception
            throw $exception;
        }
    }

    protected function moveFile(string $downloadedFile, string $applicationPath): void
    {
        clearstatcache(true, $applicationPath);

        // Fix permissions on the downloaded file as `tempnam()` creates it with 0600
        chmod($downloadedFile, 0777 - umask()); // Using the same permissions as Laravel

        rename($downloadedFile, $applicationPath);
    }

    protected function updateViaComposer(): void
    {
        // Check if the application path is writable
        if (! is_writable($this->applicationPath)) {
            throw new RuntimeException('The application path is not writable. Please rerun the command with elevated privileges.');
        }

        // Check if the directory is writable
        if (! is_writable(dirname($this->applicationPath))) {
            throw new RuntimeException('The application path is not writable. Please rerun the command with elevated privileges.');
        }

        $this->debug('Updating via Composer...');

        [$exitCode, $output] = $this->runComposerProcess();

        if ($exitCode !== 0) {
            $this->error('The Composer command failed with exit code '.$exitCode);
        }

        if (str_contains(implode("\n", $output), 'Failed to open stream: Permission denied')) {
            $this->error('The application path is not writable. Please rerun the command with elevated privileges.');
            $this->info('You can also try copying the command below and running it manually:');
            $this->warn(self::COMPOSER_COMMAND);
        }

        if ($exitCode !== 0) {
            exit($exitCode);
        }
    }

    /** @return array{0: int, 1: array<string>} */
    protected function runComposerProcess(): array
    {
        $command = self::COMPOSER_COMMAND;

        if (PHP_OS_FAMILY === 'Windows') {
            // We need to exit here so we can release the binary as Composer can't modify it when we are using it
            exit($this->runComposerWindowsProcess());
        }

        $output = [];
        $process = Process::timeout(30);

        $result = $process->run($command, function (string $type, string $buffer) use (&$output): void {
            // $this->output->writeln('<fg=gray> > '.trim($buffer).'</>');
            $output[] = $buffer;
        });

        return [$result->exitCode(), $output];
    }

    protected function runComposerWindowsProcess(): int
    {
        $command = self::COMPOSER_COMMAND;

        // We need to run Composer as an administrator on Windows, so we use PowerShell to request a UAC prompt if needed.
        $powerShell = sprintf("Start-Process -Verb RunAs powershell -ArgumentList '-Command %s'", escapeshellarg($command));
        $command = 'powershell -Command "'.$powerShell.'"';
        $this->debug("Running command: $command");
        exec($command, $output, $exitCode);

        if ($exitCode !== 0) {
            $this->error('The Composer command failed with exit code '.$exitCode);
            $this->output->writeln($output);
        } else {
            $this->info('The installation will continue in a new window as you may need to provide administrator permissions.');
        }

        return $exitCode;
    }

    protected function debug(string $message): void
    {
        if ($this->output->isVerbose()) {
            if (filled($message)) {
                $message = '<fg=gray>DEBUG:</> '.$message;
            }

            $this->output->writeln($message);
        }
    }

    protected function printNewlineIfVerbose(): void
    {
        $this->debug('');
    }

    protected static final function publicKey(): string
    {
        return <<<'TXT'
-----BEGIN PGP PUBLIC KEY BLOCK-----
Comment: 657B 4D97 184E 9E6E 596E  6EA1 3B82 9782 D5B7 BA59
Comment: HydePHP CLI Alpha Key <hello@hydephp.com>

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
=L0Bf
-----END PGP PUBLIC KEY BLOCK-----
TXT;
    }
}
