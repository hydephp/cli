<?php

declare(strict_types=1);

use App\Support\ComposerBinary;
use Tests\Support\TemporaryProject;

afterEach(function () {
    ComposerBinary::$fake = null;
    ComposerBinary::$forceMissing = false;
});

it('finds a composer executable on the search path', function () {
    $directory = TemporaryProject::directory('bin');

    file_put_contents($directory.'/composer', "#!/bin/sh\nexit 0\n");
    chmod($directory.'/composer', 0755);

    $original = getenv('PATH');

    putenv("PATH=$directory");

    try {
        expect(ComposerBinary::locate())->toBe($directory.DIRECTORY_SEPARATOR.'composer')
            ->and(ComposerBinary::available())->toBeTrue();
    } finally {
        putenv("PATH=$original");
    }
})->skipOnWindows();

it('ignores a composer file that is not executable', function () {
    $directory = TemporaryProject::directory('bin');

    file_put_contents($directory.'/composer', "#!/bin/sh\nexit 0\n");
    chmod($directory.'/composer', 0644);

    $original = getenv('PATH');

    putenv("PATH=$directory");

    try {
        expect(ComposerBinary::locate())->toBeNull();
    } finally {
        putenv("PATH=$original");
    }
})->skipOnWindows()->skip(fn () => posix_geteuid() === 0, 'Root can execute anything.');

it('reports no composer when the search path is empty', function () {
    $original = getenv('PATH');

    putenv('PATH=');

    try {
        expect(ComposerBinary::locate())->toBeNull()
            ->and(ComposerBinary::available())->toBeFalse();
    } finally {
        putenv("PATH=$original");
    }
});

it('can be forced to report composer as missing', function () {
    ComposerBinary::$forceMissing = true;

    expect(ComposerBinary::locate())->toBeNull();
});
