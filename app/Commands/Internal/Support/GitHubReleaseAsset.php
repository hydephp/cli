<?php

declare(strict_types=1);

namespace App\Commands\Internal\Support;

use Illuminate\Support\Arr;
use InvalidArgumentException;

use function implode;
use function array_keys;
use function array_diff;

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
        $requiredFields = ['url', 'id', 'node_id', 'name', 'label', 'uploader', 'content_type', 'state', 'size', 'download_count', 'created_at', 'updated_at', 'browser_download_url'];

        if (! Arr::has($data, $requiredFields)) {
            throw new InvalidArgumentException('Missing required field(s): '.implode(', ', array_diff($requiredFields, array_keys($data))));
        }
    }
}
