<?php

declare(strict_types=1);

namespace App\Commands\Internal\Support;

use InvalidArgumentException;

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
        // Perform validation based on the GitHub API response structure schema.

        $requiredFields = [
            'url', 'html_url', 'assets_url', 'upload_url', 'tarball_url',
            'zipball_url', 'id', 'node_id', 'tag_name', 'target_commitish',
            'draft', 'prerelease', 'created_at', 'author', 'assets'
        ];

        foreach ($requiredFields as $field) {
            if (! isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
        }
    }
}
