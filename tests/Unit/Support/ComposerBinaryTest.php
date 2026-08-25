<?php

declare(strict_types=1);

use App\Support\ComposerBinary;
use App\Launcher\RuntimeManager;
use Tests\Support\TemporaryProject;

/** A runtime that bundles nothing, standing in for a source checkout. */
function bareRuntime(): RuntimeManager
{
    return new RuntimeManager(null, TemporaryProject::directory('bare-runtime'));
}

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

it('reports no composer when the search path is empty and none is bundled', function () {
    $original = getenv('PATH');

    putenv('PATH=');

    try {
        expect(ComposerBinary::locate())->toBeNull()
            ->and(ComposerBinary::available(bareRuntime()))->toBeFalse()
            ->and(ComposerBinary::command(bareRuntime()))->toBeNull();
    } finally {
        putenv("PATH=$original");
    }
});

/*
|--------------------------------------------------------------------------
| Which Composer gets used
|--------------------------------------------------------------------------
|
| The one inside the executable, whenever there is one: it is the release this
| build was made against, and it is there on a machine that has no Composer.
|
*/

it('prefers the bundled composer over one on the search path', function () {
    $directory = TemporaryProject::directory('bin');

    file_put_contents($directory.'/composer', "#!/bin/sh\nexit 0\n");
    chmod($directory.'/composer', 0755);

    $root = TemporaryProject::directory('bundled');

    mkdir($root.'/'.RuntimeManager::RUNTIME_DIRECTORY);

    file_put_contents($root.'/'.RuntimeManager::RUNTIME_DIRECTORY.'/'.RuntimeManager::COMPOSER_FILE.RuntimeManager::RUNTIME_SUFFIX, gzencode('<?php // composer'));

    file_put_contents($root.'/'.RuntimeManager::RUNTIME_DIRECTORY.'/'.RuntimeManager::MANIFEST_FILE, json_encode([
        'version' => PHP_VERSION,
        'checksum' => '',
        'composer' => ['version' => '2.8.12', 'filename' => RuntimeManager::COMPOSER_FILE, 'checksum' => hash('sha256', '<?php // composer')],
    ]));

    $runtime = new RuntimeManager(null, $root);

    $original = getenv('PATH');

    putenv("PATH=$directory");

    try {
        // The runtime first, then the PHAR: a bundled Composer is never run on its own.
        expect(ComposerBinary::command($runtime))
            ->toBe([$runtime->path(), $runtime->composerPath()])
            ->and(ComposerBinary::bundled($runtime))->toBe(ComposerBinary::command($runtime));
    } finally {
        putenv("PATH=$original");
    }
})->skipOnWindows();

it('falls back to the host composer when nothing is bundled', function () {
    $directory = TemporaryProject::directory('bin');

    file_put_contents($directory.'/composer', "#!/bin/sh\nexit 0\n");
    chmod($directory.'/composer', 0755);

    $original = getenv('PATH');

    putenv("PATH=$directory");

    try {
        expect(ComposerBinary::bundled(bareRuntime()))->toBeNull()
            ->and(ComposerBinary::command(bareRuntime()))->toBe([$directory.DIRECTORY_SEPARATOR.'composer']);
    } finally {
        putenv("PATH=$original");
    }
})->skipOnWindows();

it('can be forced to report composer as missing', function () {
    ComposerBinary::$forceMissing = true;

    expect(ComposerBinary::locate())->toBeNull();
});
