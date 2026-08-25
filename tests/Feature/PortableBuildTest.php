<?php

declare(strict_types=1);

use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| Building a portable project
|--------------------------------------------------------------------------
|
| These run the real build command against real directories, through the same
| application the executable boots. The integration suite repeats them against
| the built artifact; here they run fast enough to be useful while working.
|
*/

it('builds a portable project to _site', function () {
    $path = TemporaryProject::portable(['_pages/index.md' => "---\ntitle: Test Page\n---\n\n# Hello Portable World\n"]);

    $this->boot($path);

    expect($this->runCommand('build'))->toBe(0)
        ->and($path.'/_site/index.html')->toBeFile()
        ->and(file_get_contents($path.'/_site/index.html'))
        ->toContain('Hello Portable World')
        ->toContain('Test Page');
});

it('builds the bundled stylesheet locally without changing the source media directory', function () {
    $path = TemporaryProject::portable();

    $this->boot($path);

    expect($this->runCommand('build'))->toBe(0)
        ->and($path.'/_media/app.css')->not->toBeFile()
        ->and($path.'/_site/media/app.css')->toBeFile()
        ->and(file_get_contents($path.'/_site/index.html'))
        ->toContain('media/app.css')
        ->and(file_get_contents($path.'/_site/media/app.css'))
        ->toContain('tailwindcss');
});

it('prefers and preserves a user stylesheet', function () {
    $userStylesheet = '/* user stylesheet */\nbody { color: rebeccapurple; }\n';
    $path = TemporaryProject::portable(['_media/app.css' => $userStylesheet]);

    $this->boot($path);

    expect($this->runCommand('build'))->toBe(0)
        ->and(file_get_contents($path.'/_site/media/app.css'))->toBe($userStylesheet)
        ->and(file_get_contents($path.'/_media/app.css'))->toBe($userStylesheet);
});

it('produces the same bundled stylesheet on repeated builds', function () {
    $path = TemporaryProject::portable();

    $this->boot($path);

    expect($this->runCommand('build'))->toBe(0);
    $first = file_get_contents($path.'/_site/media/app.css');

    expect($this->runCommand('build'))->toBe(0)
        ->and(file_get_contents($path.'/_site/media/app.css'))->toBe($first);
});

it('never creates a vendor directory or a composer manifest', function () {
    $path = TemporaryProject::portable();

    $this->boot($path);

    $this->runCommand('build');

    expect($path.'/vendor')->not->toBeDirectory()
        ->and($path.'/composer.json')->not->toBeFile()
        ->and($path.'/composer.lock')->not->toBeFile();
});

it('writes its caches outside the project', function () {
    $path = TemporaryProject::portable();

    $this->boot($path);

    $this->runCommand('build');

    // The build manifest is the one cache a portable project does keep, and it goes to a
    // dedicated directory rather than the `app/storage` tree a portable project lacks.
    expect($path.'/app')->not->toBeDirectory()
        ->and($path.'/.hyde-cache/build-manifest.json')->toBeFile();
});

it('honours hyde.yml configuration', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "# Home\n",
        '_pages/site-name.blade.php' => '{{ config("hyde.name", "not-set") }}',
        'hyde.yml' => "name: \"Configured Site Name\"\n",
    ]);

    $this->boot($path);

    expect($this->runCommand('build'))->toBe(0)
        ->and(file_get_contents($path.'/_site/site-name.html'))->toContain('Configured Site Name');
});

it('lists the routes of a portable project', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "# Home\n",
        '_pages/about.md' => "# About\n",
        '_posts/hello-world.md' => "# Hello\n",
        '_docs/index.md' => "# Docs\n",
    ]);

    $this->boot($path);

    expect($this->runCommand('route:list'))->toBe(0)
        ->and($this->consoleOutput())
        ->toContain('_pages/index.md')
        ->toContain('_pages/about.md')
        ->toContain('_posts/hello-world.md')
        ->toContain('_docs/index.md');
});

it('scaffolds a page into a portable project', function () {
    $path = TemporaryProject::portable();

    $this->boot($path);

    expect($this->runCommand('make:page', ['title' => 'About', '--no-interaction' => true]))->toBe(0)
        ->and($path.'/_pages/about.md')->toBeFile()
        ->and(file_get_contents($path.'/_pages/about.md'))->toContain('About');
});

it('stays portable when an unrelated composer manifest sits beside the content', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "# Home\n",
        'composer.json' => '{"name": "acme/thing", "description": "mentions hyde/framework", "require": {"monolog/monolog": "^3.0"}}',
    ]);

    $this->boot($path);

    expect($this->app->make(App\Launcher\Project::class)->isPortable())->toBeTrue()
        ->and($this->runCommand('build'))->toBe(0)
        ->and($path.'/_site/index.html')->toBeFile()
        ->and($path.'/vendor')->not->toBeDirectory();
});
