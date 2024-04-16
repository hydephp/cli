<?php

/** @noinspection PhpComposerExtensionStubsInspection as we have our own extension check */

declare(strict_types=1);

namespace App\Commands;

use Throwable;
use App\Application;
use RuntimeException;
use Illuminate\Support\Str;
use Illuminate\Console\Command;

use function fopen;
use function assert;
use function fclose;
use function rename;
use function explode;
use function ini_set;
use function sprintf;
use function implode;
use function tempnam;
use function passthru;
use function array_map;
use function curl_init;
use function curl_exec;
use function urlencode;
use function curl_close;
use function array_keys;
use function json_decode;
use function is_writable;
use function curl_setopt;
use function array_combine;
use function sys_get_temp_dir;
use function extension_loaded;
use function file_get_contents;
use function get_included_files;

/**
 * @experimental This command is highly experimental and may contain bugs.
 */
class SelfUpdateCommand extends Command
{
    /** @var string */
    protected $signature = 'self-update';

    /** @var string */
    protected $description = 'Update the standalone application to the latest version.';

    protected const STATE_BEHIND = 1;
    protected const STATE_UP_TO_DATE = 2;
    protected const STATE_AHEAD = 3;

    protected const STRATEGY_DIRECT = 'direct';
    protected const STRATEGY_COMPOSER = 'composer';

    /** @var array<string, string|array<string>> The latest release information from the GitHub API */
    protected array $release;

    public function handle(): int
    {
        try {
            $this->output->title('Checking for a new version...');

            $applicationPath = $this->findApplicationPath();
            $this->debug("Application path: $applicationPath");

            $strategy = $this->determineUpdateStrategy($applicationPath);
            $this->debug('Update strategy: '.($strategy === self::STRATEGY_COMPOSER ? 'Composer' : 'Direct download'));

            $currentVersion = $this->parseVersion(Application::APP_VERSION);
            $this->debug('Current version: v'.implode('.', $currentVersion));

            $latestVersion = $this->parseVersion($this->getLatestReleaseVersion());
            $this->debug('Latest version: v'.implode('.', $latestVersion));

            // Add a newline for better readability
            $this->debug();

            $state = $this->compareVersions($currentVersion, $latestVersion);
            $this->printVersionStateInformation($state);

            if ($state !== self::STATE_BEHIND) {
                return Command::SUCCESS;
            }

            $this->output->title('Updating to the latest version...');

            $this->updateApplication($strategy);

            // Add a newline for better readability
            $this->debug();

            $this->info('The application has been updated successfully.');

            return Command::SUCCESS;
        } catch (Throwable $exception) {
            $this->error('Something went wrong while updating the application. As the self-update command is experimental, this may be a bug within the command itself. Please report this issue on GitHub so we can fix it!');

            $environment = implode("\n", [
                'Application version: v'.Application::APP_VERSION,
                'PHP version:         v'.PHP_VERSION,
                'Operating system:    '.PHP_OS,
            ]);

            $this->warn($this->buildUrl('https://github.com/hydephp/cli/issues/new', [
                'title' => 'Error while self-updating the application',
                'body' => <<<MARKDOWN
                ### Description
                
                A fatal error occurred while trying to update the application using the self-update command.
                
                ### Error message
                
                ```
                {$exception->getMessage()}
                ```
                
                ### Stack trace
                
                ```
                {$exception->getTraceAsString()}
                ```
                
                ### Environment
                
                ```
                $environment
                ```
                
                ### Context
                
                - Add any additional context here that may be relevant to the issue.
                
                MARKDOWN
            ]));
            $this->output->warning('Here is what went wrong:');

            throw $exception;
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

        assert($data !== null);
        assert(isset($data['tag_name']));
        assert(isset($data['assets']));
        assert(isset($data['assets'][0]));
        assert(isset($data['assets'][0]['browser_download_url']));
        assert(isset($data['assets'][0]['name']) && $data['assets'][0]['name'] === 'hyde');

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
        // Get the full path to the application executable
        // Generally /user/bin/hyde, /usr/local/bin/hyde, or C:\Users\User\AppData\Roaming\Composer\vendor\bin\hyde

        return get_included_files()[0];
    }

    /** @param self::STATE_* $state */
    protected function printVersionStateInformation(int $state): void
    {
        match ($state) {
            self::STATE_BEHIND => $this->info('A new version is available.'),
            self::STATE_UP_TO_DATE => $this->info('You are already using the latest version.'),
            self::STATE_AHEAD => $this->info('You are using a development version.'),
        };
    }

    /** @param self::STRATEGY_* $strategy */
    protected function updateApplication(string $strategy): void
    {
        $this->output->writeln('Updating the application...');

        match ($strategy) {
            self::STRATEGY_DIRECT => $this->updateDirectly(),
            self::STRATEGY_COMPOSER => $this->updateViaComposer(),
        };
    }

    /** @return self::STRATEGY_* */
    protected function determineUpdateStrategy(string $applicationPath): string
    {
        // Check if the application is installed via Composer
        if (Str::contains($applicationPath, 'composer', true)) {
            return self::STRATEGY_COMPOSER;
        }

        // Check that the executable path is writable
        if (! is_writable($applicationPath)) {
            throw new RuntimeException('The application path is not writable. Please rerun the command with elevated privileges.');
        }

        // Check that the Curl extension is available
        if (! extension_loaded('curl')) {
            throw new RuntimeException('The Curl extension is required to use the self-update command.');
        }

        return self::STRATEGY_DIRECT;
    }

    protected function updateDirectly(): void
    {
        $this->output->writeln('Downloading the latest version...');

        // Download the latest release from GitHub
        $downloadUrl = $this->release['assets'][0]['browser_download_url'];
        $downloadedFile = tempnam(sys_get_temp_dir(), 'hyde');
        $this->downloadFile($downloadUrl, $downloadedFile);

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
        $applicationPath = $this->findApplicationPath();

        $this->debug("Moving file $downloadedFile to $applicationPath");

        // Replace the current application with the downloaded one
        rename($downloadedFile, $applicationPath);
    }

    protected function updateViaComposer(): void
    {
        $this->output->writeln('Updating via Composer...');

        // Invoke the Composer command to update the application
        passthru('composer global update hyde/hyde');
    }

    protected function debug(string $message = ''): void
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln($message);
        }
    }

    /** @param array<string, string> $params */
    protected function buildUrl(string $url, array $params): string
    {
        return sprintf("$url?%s", implode('&', array_map(function (string $key, string $value): string {
            return sprintf('%s=%s', $key, urlencode($value));
        }, array_keys($params), $params)));
    }
}
