<?php

declare(strict_types=1);

namespace App\Commands\Internal\Support;

use InvalidArgumentException;

/**
 * @internal Helper class to provide a typed representation of a GitHub release asset in the GitHubReleaseData class.
 *
 * @see \App\Commands\Internal\Support\GitHubReleaseData
 */
class GitHubReleaseAsset
{
    public function __construct(array $data)
    {
        $this->validate($data);
    }

    protected function validate(array $data): void
    {
        // Perform validation based on the GitHub API response structure schema.

        $requiredFields = [
            'url', 'id', 'node_id', 'name', 'label', 'uploader', 'content_type',
            'state', 'size', 'download_count', 'created_at', 'updated_at', 'browser_download_url'
        ];

        foreach ($requiredFields as $field) {
            if (! isset($data[$field])) {
                throw new InvalidArgumentException("Missing required field: $field");
            }
        }
    }
}
