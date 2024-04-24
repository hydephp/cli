<?php

use Illuminate\Support\Arr;
use App\Commands\Internal\Support\GitHubReleaseData;
use App\Commands\Internal\Support\GitHubReleaseAsset;

it('creates a GitHubRelease object from JSON data', function () {
    expect(new GitHubReleaseData(getFixture()))
        ->toBeInstanceOf(GitHubReleaseData::class);
});

it('creates a GitHubReleaseAsset object from JSON data', function () {
    expect(new GitHubReleaseAsset(getFixture('assets.0')))
        ->toBeInstanceOf(GitHubReleaseAsset::class);
});

it('constructs semver tag', function () {
    $release = new GitHubReleaseData(getFixture());

    expect($release->tag)->toBe('v1.0.0');
});

it('constructs assets', function () {
    $release = new GitHubReleaseData(getFixture());

    expect($release->assets)
        ->toHaveCount(1)
        ->toHaveKeys(['example.zip'])
        ->toContainOnlyInstancesOf(GitHubReleaseAsset::class);
});

test('data class throws an exception when required fields are missing', function () {
    new GitHubReleaseData([]);
})->throws(InvalidArgumentException::class);

test('asset class throws an exception when required fields are missing', function () {
    new GitHubReleaseAsset([]);
})->throws(InvalidArgumentException::class);

test('data class throws an exception when required field is missing', function () {
    $data = getFixture();
    array_shift($data);
    new GitHubReleaseData($data);
})->throws(InvalidArgumentException::class);

test('asset class throws an exception when required field is missing', function () {
    $data = getFixture('assets.0');
    array_shift($data);
    new GitHubReleaseAsset($data);
})->throws(InvalidArgumentException::class);

function getFixture(?string $selector = null): array
{
    if ($selector !== null) {
        return Arr::get(getFixture(), $selector);
    }

    return fixture('github-release-api-sample-response.json');
}
