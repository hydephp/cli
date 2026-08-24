<?php

declare(strict_types=1);

use App\Support\PortableProjectBuilder;
use Tests\Support\TemporaryProject;

it('creates the directories a portable site needs', function () {
    $path = TemporaryProject::directory('new-site');

    (new PortableProjectBuilder($path, 'my-blog'))->create();

    expect($path.'/_pages')->toBeDirectory()
        ->and($path.'/_posts')->toBeDirectory()
        ->and($path.'/_media')->toBeDirectory()
        // v3 empties the output directory completely, so `_static` is the only place a
        // file that must reach the compiled site root can live.
        ->and($path.'/_static')->toBeDirectory();
});

it('creates a homepage that carries the project name', function () {
    $path = TemporaryProject::directory('new-site');

    (new PortableProjectBuilder($path, 'my-blog'))->create();

    expect($path.'/_pages/index.md')->toBeFile()
        ->and(file_get_contents($path.'/_pages/index.md'))->toContain('My Blog');
});

it('configures the site through hyde.yml', function () {
    $path = TemporaryProject::directory('new-site');

    (new PortableProjectBuilder($path, 'my-blog'))->create();

    expect(file_get_contents($path.'/hyde.yml'))->toContain('name: "My Blog"');
});

it('never creates a composer manifest or a vendor directory', function () {
    $path = TemporaryProject::directory('new-site');

    (new PortableProjectBuilder($path, 'my-blog'))->create();

    expect($path.'/composer.json')->not->toBeFile()
        ->and($path.'/composer.lock')->not->toBeFile()
        ->and($path.'/vendor')->not->toBeDirectory();
});

it('ignores build output in version control', function () {
    $path = TemporaryProject::directory('new-site');

    (new PortableProjectBuilder($path, 'my-blog'))->create();

    expect(file_get_contents($path.'/.gitignore'))->toContain('/_site');
});

it('derives a title from the directory when no name is given', function () {
    $path = TemporaryProject::directory('my-lovely-site');

    expect((new PortableProjectBuilder($path))->title())->toContain('My Lovely Site');
});

it('derives a title from the last segment of a nested name', function () {
    $path = TemporaryProject::directory('new-site');

    expect((new PortableProjectBuilder($path, 'sites/my-blog'))->title())->toBe('My Blog');
});
