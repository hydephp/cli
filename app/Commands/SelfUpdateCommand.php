<?php

declare(strict_types=1);

namespace App\Commands;

use App\Application;
use Illuminate\Console\Command;

use function explode;
use function array_map;
use function array_combine;

class SelfUpdateCommand extends Command
{
    /** @var string */
    protected $signature = 'self-update';

    /** @var string */
    protected $description = 'Update the standalone application to the latest version.';

    /** @var array{major: int, minor: int, patch: int} */
    protected array $currentVersion;

    public function handle(): void
    {
        $this->output->title('Checking for a new version...');

        $this->currentVersion = array_combine(['major', 'minor', 'patch'], array_map('intval', explode('.', Application::APP_VERSION)));

        $latestVersion = $this->getLatestVersion();
    }

    protected function getLatestVersion()
    {
        // Set the user agent as required by the GitHub API
        ini_set('user_agent', $this->getUserAgent());

        $response = file_get_contents('https://api.github.com/repos/hydephp/cli/releases/latest');

        $latestVersion = json_decode($response, true)['tag_name'];

        return array_combine(['major', 'minor', 'patch'], array_map('intval', explode('.', $latestVersion)));
    }

    protected function getUserAgent(): string
    {
        return sprintf('HydePHP CLI updater v%s (github.com/hydephp/cli)', Application::APP_VERSION);
    }
}
