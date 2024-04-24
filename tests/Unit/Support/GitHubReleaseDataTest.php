<?php

use App\Commands\Internal\Support\GitHubReleaseData;
use App\Commands\Internal\Support\GitHubReleaseAsset;

beforeEach(function () {
    $this->data = fixture('github-release-api-sample-response.json');
});

it('creates a GitHubRelease object from JSON data', function () {
    expect(new GitHubReleaseData($this->data))
        ->toBeInstanceOf(GitHubReleaseData::class);
});

it('creates a GitHubReleaseAsset object from JSON data', function () {
    expect(new GitHubReleaseAsset($this->data['assets'][0]))
        ->toBeInstanceOf(GitHubReleaseAsset::class);
});

it('constructs semver tag', function () {
    expect((new GitHubReleaseData($this->data))->tag)
        ->toBe('v1.0.0');
});

it('constructs assets', function () {
    $release = new GitHubReleaseData($this->data);

    expect($release->assets)
        ->toHaveCount(1)
        ->toHaveKeys(['example.zip'])
        ->toContainOnlyInstancesOf(GitHubReleaseAsset::class);
});

it('constructs asset name', function () {
    expect((new GitHubReleaseAsset($this->data['assets'][0]))->name)
        ->toBe('example.zip');
});

it('constructs asset URL', function () {
    expect((new GitHubReleaseAsset($this->data['assets'][0]))->url)
        ->toBe('https://github.com/octocat/Hello-World/releases/download/v1.0.0/example.zip');
});

test('data class throws an exception when required fields are missing', function () {
    new GitHubReleaseData([]);
})->throws(InvalidArgumentException::class);

test('asset class throws an exception when required fields are missing', function () {
    new GitHubReleaseAsset([]);
})->throws(InvalidArgumentException::class);

test('data class throws an exception when required field is missing', function () {
    array_shift($this->data);
    new GitHubReleaseData($this->data);
})->throws(InvalidArgumentException::class);

test('asset class throws an exception when required field is missing', function () {
    array_shift($this->data['assets'][0]);
    new GitHubReleaseAsset($this->data['assets'][0]);
})->throws(InvalidArgumentException::class);
