<?php

declare(strict_types=1);

namespace App\Commands\Internal\Support;

use Illuminate\Support\Arr;
use InvalidArgumentException;

use function implode;
use function array_keys;
use function array_diff;

/**
 * @internal Helper class for the self-update command to wrap the GitHub release data API response.
 *
 * @link https://docs.github.com/en/rest/releases/releases?apiVersion=2022-11-28#get-the-latest-release
 */
class GitHubReleaseData
{
    public function __construct(array $data)
    {
        $this->validate($data);
    }

    protected function validate(array $data): void
    {
        $requiredFields = ['url', 'html_url', 'assets_url', 'upload_url', 'tarball_url', 'zipball_url', 'id', 'node_id', 'tag_name', 'target_commitish', 'draft', 'prerelease', 'created_at', 'author', 'assets'];

        if (! Arr::has($data, $requiredFields)) {
            throw new InvalidArgumentException('Missing required field(s): '.implode(', ', array_diff($requiredFields, array_keys($data))));
        }
    }
}
