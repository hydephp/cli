<?php

declare(strict_types=1);

use App\Launcher\ComposerManifest;
use App\Launcher\LauncherException;
use Tests\Support\TemporaryProject;

it('reads the Hyde requirements out of both requirement graphs', function () {
    $path = TemporaryProject::directory();

    TemporaryProject::write($path, ['composer.json' => json_encode([
        'require' => ['php' => '^8.2', 'hyde/framework' => '^2.0'],
        'require-dev' => ['hyde/hyde' => 'dev-master', 'pestphp/pest' => '^3.0'],
    ])]);

    expect(ComposerManifest::read($path.'/composer.json')->hydeRequirements())
        ->toBe(['hyde/framework' => '^2.0', 'hyde/hyde' => 'dev-master']);
});

it('reports no Hyde requirement for an unrelated manifest', function () {
    $path = TemporaryProject::directory();

    TemporaryProject::write($path, ['composer.json' => '{"require": {"monolog/monolog": "^3.0"}}']);

    expect(ComposerManifest::read($path.'/composer.json')->declaresHyde())->toBeFalse();
});

it('throws for a manifest that is not valid JSON', function () {
    $path = TemporaryProject::directory();

    TemporaryProject::write($path, ['composer.json' => 'not json at all']);

    expect(fn () => ComposerManifest::read($path.'/composer.json'))
        ->toThrow(LauncherException::class, 'could not be parsed');
});

it('throws for a manifest that cannot be read', function () {
    expect(fn () => ComposerManifest::read('/definitely/not/here/composer.json'))
        ->toThrow(LauncherException::class, 'Unable to read');
});

it('tolerates a requirement section that is not a map', function () {
    $path = TemporaryProject::directory();

    TemporaryProject::write($path, ['composer.json' => '{"require": ["hyde/framework"]}']);

    expect(ComposerManifest::read($path.'/composer.json')->declaresHyde())->toBeFalse();
});

/*
|--------------------------------------------------------------------------
| Reading the pinned framework version
|--------------------------------------------------------------------------
*/

it('reads the installed framework version from the lock file', function () {
    $path = TemporaryProject::directory();

    TemporaryProject::write($path, ['composer.lock' => json_encode([
        'packages' => [
            ['name' => 'illuminate/support', 'version' => 'v11.0.0'],
            ['name' => 'hyde/framework', 'version' => 'v2.0.3'],
        ],
    ])]);

    expect(ComposerManifest::lockedVersion($path.'/composer.lock'))->toBe('2.0.3');
});

it('reads a framework installed as a development dependency', function () {
    $path = TemporaryProject::directory();

    TemporaryProject::write($path, ['composer.lock' => json_encode([
        'packages' => [],
        'packages-dev' => [['name' => 'hyde/framework', 'version' => 'v2.1.0']],
    ])]);

    expect(ComposerManifest::lockedVersion($path.'/composer.lock'))->toBe('2.1.0');
});

it('reports no version when there is no lock file', function () {
    expect(ComposerManifest::lockedVersion('/definitely/not/here/composer.lock'))->toBeNull();
});

it('reports no version when the lock file does not contain the framework', function () {
    $path = TemporaryProject::directory();

    TemporaryProject::write($path, ['composer.lock' => '{"packages": []}']);

    expect(ComposerManifest::lockedVersion($path.'/composer.lock'))->toBeNull();
});

it('reports no version when the lock file is corrupt', function () {
    $path = TemporaryProject::directory();

    TemporaryProject::write($path, ['composer.lock' => 'not json']);

    expect(ComposerManifest::lockedVersion($path.'/composer.lock'))->toBeNull();
});
