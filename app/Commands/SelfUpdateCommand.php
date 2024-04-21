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
        $downloadUrl = $this->release['assets'][0]['browser_download_url'];
        $downloadedFile = $tempPath.'.phar';
        $this->downloadFile($downloadUrl, $downloadedFile);

        // Make the downloaded file executable
        chmod($downloadedFile, 0755);

        // Replace the current application with the downloaded one
        $this->replaceApplication($downloadedFile);
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
            // We need to run Composer as an administrator on Windows, so we use PowerShell to request a UAC prompt if needed.
            $powerShell = sprintf("Start-Process -Verb RunAs powershell -ArgumentList '-Command %s'", escapeshellarg($command));
            $command = 'powershell -Command "'.$powerShell.'"';
            $this->debug("Running command: $command");
            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                $this->error('The Composer command failed with exit code '.$exitCode);
                $this->output->writeln($output);
                exit($exitCode);
            } else {
                $this->info('The installation will continue in a new window as you may need to provide administrator permissions.');
                // We need to exit here so we can release the binary as Composer can't modify it when we are using it
                exit(0);
            }
        }

        $output = [];
        $process = Process::timeout(30);

        $result = $process->run($command, function (string $type, string $buffer) use (&$output): void {
            // $this->output->writeln('<fg=gray> > '.trim($buffer).'</>');
            $output[] = $buffer;
        });

        return [$result->exitCode(), $output];
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
}
