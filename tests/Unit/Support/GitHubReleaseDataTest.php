<?php

use App\Commands\Internal\Support\GitHubReleaseData;
use App\Commands\Internal\Support\GitHubReleaseAsset;

it('creates a GitHubRelease object from JSON data', function () {
    $data = fixture('github-release-api-sample-response.json');

    $release = new GitHubReleaseData($data);

    expect($release)->toBeInstanceOf(GitHubReleaseData::class);
});

it('creates a GitHubReleaseAsset object from JSON data', function () {
    $data = fixture('github-release-api-sample-response.json');

    $asset = new GitHubReleaseAsset($data['assets'][0]);

    expect($asset)->toBeInstanceOf(GitHubReleaseAsset::class);
});
