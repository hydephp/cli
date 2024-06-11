<?php

declare(strict_types=1);

namespace App\Commands\Internal\Support;

use Illuminate\Support\Arr;
use InvalidArgumentException;

use function array_combine;
use function array_diff;
use function array_keys;
use function array_map;
use function implode;

/**
 * @internal Helper class for the self-update command to wrap the GitHub release data API response.
 *
 * @link https://docs.github.com/en/rest/releases/releases?apiVersion=2022-11-28#get-the-latest-release
 */
class GitHubReleaseData
{
    /** @var string The SemVer tag of the release */
    public readonly string $tag;

    /** @var array<string, \App\Commands\Internal\Support\GitHubReleaseAsset> The assets of the release, keyed by their name */
    public readonly array $assets;

    public function __construct(array $data)
    {
        $this->validate($data);

        $this->tag = $data['tag_name'];

        $this->assets = array_combine(
            array_map(fn (array $asset) => $asset['name'], $data['assets']),
            array_map(fn (array $asset) => new GitHubReleaseAsset($asset), $data['assets'])
        );
    }

    protected function validate(array $data): void
    {
        $requiredFields = ['url', 'html_url', 'assets_url', 'upload_url', 'tarball_url', 'zipball_url', 'id', 'node_id', 'tag_name', 'target_commitish', 'draft', 'prerelease', 'created_at', 'author', 'assets'];

        if (! Arr::has($data, $requiredFields)) {
            throw new InvalidArgumentException('Missing required field(s): '.implode(', ', array_diff($requiredFields, array_keys($data))));
        }
    }

    public function getAsset(string $name): GitHubReleaseAsset
    {
        return $this->assets[$name] ?? throw new InvalidArgumentException('Asset not found: '.$name);
    }
}
