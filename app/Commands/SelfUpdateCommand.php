<?php

declare(strict_types=1);

namespace App\Commands;

use App\Application;
use Illuminate\Console\Command;

use function explode;
use function ini_set;
use function sprintf;
use function implode;
use function array_map;
use function json_decode;
use function array_combine;
use function file_get_contents;

class SelfUpdateCommand extends Command
{
    /** @var string */
    protected $signature = 'self-update';

    /** @var string */
    protected $description = 'Update the standalone application to the latest version.';

    protected const STATE_BEHIND = 1;
    protected const STATE_UP_TO_DATE = 2;
    protected const STATE_AHEAD = 3;

    public function handle(): int
    {
        $this->output->title('Checking for a new version...');

        $applicationPath = $this->findApplicationPath();
        $this->debug("Application path: $applicationPath");

        $currentVersion = $this->parseVersion(Application::APP_VERSION);
        $this->debug('Current version: v'.implode('.', $currentVersion));

        $latestVersion = $this->parseVersion($this->getLatestReleaseVersion());
        $this->debug('Latest version: v'.implode('.', $latestVersion));

        $state = $this->compareVersions($currentVersion, $latestVersion);
        $this->printVersionStateInformation($state);

        if ($state !== self::STATE_BEHIND) {
            return Command::SUCCESS;
        }

        $this->output->title('Updating to the latest version...');

        $this->updateApplication();

        $this->info('The application has been updated successfully.');

        return Command::SUCCESS;
    }

    protected function getLatestReleaseVersion(): string
    {
        // Set the user agent as required by the GitHub API
        ini_set('user_agent', $this->getUserAgent());

        $response = file_get_contents('https://api.github.com/repos/hydephp/cli/releases/latest');

        return json_decode($response, true)['tag_name'];
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

    protected function updateApplication(): void
    {
        // Todo
    }

    protected function debug(string $message): void
    {
        if ($this->output->isVerbose()) {
            $this->output->writeln("$message");
        }
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
}
