<?php

declare(strict_types=1);

use Tests\Support\Executable;
use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| The native executable
|--------------------------------------------------------------------------
|
| Every test here runs the real artifact with an environment that contains no
| PHP and no Composer, which is the guarantee the whole build exists to make.
|
*/

beforeEach(function () {
    if (Executable::path() === null) {
        $this->markTestSkipped(Executable::missingMessage());
    }
});

it('runs with no PHP available on the search path', function () {
    // Guard the guard: if this ever finds a PHP, the rest of the suite proves nothing.
    $probe = shell_exec('env -i PATH='.Executable::CLEAN_PATH.' sh -c "command -v php || echo none"');

    expect(trim((string) $probe))->toBe('none');

    $result = Executable::run(['--no-ansi'], TemporaryProject::portable());

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('USAGE:');
})->skipOnWindows();

it('reports its version with no PHP available', function () {
    $result = Executable::run(['--version', '--no-ansi'], TemporaryProject::portable());

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain(App\Application::APP_VERSION)
        ->and($result['output'])->toContain(Hyde\Foundation\HydeKernel::VERSION);
});

it('reports a bundled PHP runtime', function () {
    $result = Executable::run(['info', '--no-ansi'], TemporaryProject::portable());

    expect($result['output'])
        ->toContain('Project type: Portable')
        ->toContain('Framework:')
        ->toContain(Hyde\Foundation\HydeKernel::VERSION.' (embedded)')
        ->toContain('(bundled)');
});

/*
|--------------------------------------------------------------------------
| Building a portable project
|--------------------------------------------------------------------------
*/

it('builds a portable project to the expected output', function () {
    $path = TemporaryProject::portable(['_pages/index.md' => "---\ntitle: Test Page\n---\n\n# Hello Portable World\n"]);

    $result = Executable::run(['build', '--no-ansi'], $path);

    expect($result['status'])->toBe(0)
        ->and($result['output'])->toContain('Your static site has been built!')
        ->and($path.'/_site/index.html')->toBeFile()
        ->and(file_get_contents($path.'/_site/index.html'))
        ->toContain('Hello Portable World')
        ->toContain('Test Page');
});

it('never creates a vendor directory or a composer manifest while building', function () {
    $path = TemporaryProject::portable();

    Executable::run(['build', '--no-ansi'], $path);
    Executable::run(['make:page', 'About', '--no-ansi', '--no-interaction'], $path);
    Executable::run(['route:list', '--no-ansi'], $path);

    expect($path.'/vendor')->not->toBeDirectory()
        ->and($path.'/composer.json')->not->toBeFile()
        ->and($path.'/composer.lock')->not->toBeFile();
});

it('honours hyde.yml configuration in a portable project', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "# Home\n",
        '_pages/site-name.blade.php' => '{{ config("hyde.name", "not-set") }}',
        'hyde.yml' => "name: \"Configured Site Name\"\n",
    ]);

    expect(Executable::run(['build', '--no-ansi'], $path)['status'])->toBe(0)
        ->and(file_get_contents($path.'/_site/site-name.html'))->toContain('Configured Site Name');
});

it('lists the routes of a portable project', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "# Home\n",
        '_pages/about.md' => "# About\n",
        '_posts/hello-world.md' => "# Hello\n",
    ]);

    $result = Executable::run(['route:list', '--no-ansi'], $path);

    expect($result['status'])->toBe(0)
        ->and($result['output'])
        ->toContain('_pages/index.md')
        ->toContain('_pages/about.md')
        ->toContain('_posts/hello-world.md');
});

it('scaffolds a page in a portable project', function () {
    $path = TemporaryProject::portable();

    $result = Executable::run(['make:page', 'About', '--no-ansi', '--no-interaction'], $path);

    expect($result['status'])->toBe(0)
        ->and($path.'/_pages/about.md')->toBeFile()
        ->and(file_get_contents($path.'/_pages/about.md'))->toContain('About');
});

/*
|--------------------------------------------------------------------------
| Creating projects
|--------------------------------------------------------------------------
*/

it('creates a portable project that builds immediately', function () {
    $parent = TemporaryProject::directory('workspace');

    $created = Executable::run(['new', 'my-site', '--portable', '--no-ansi', '--no-interaction'], $parent);

    expect($created['status'])->toBe(0)
        ->and($parent.'/my-site/_pages/index.md')->toBeFile()
        ->and($parent.'/my-site/composer.json')->not->toBeFile()
        ->and($parent.'/my-site/vendor')->not->toBeDirectory();

    $built = Executable::run(['build', '--no-ansi'], $parent.'/my-site');

    expect($built['status'])->toBe(0)
        ->and($parent.'/my-site/_site/index.html')->toBeFile()
        ->and($parent.'/my-site/composer.json')->not->toBeFile()
        ->and($parent.'/my-site/vendor')->not->toBeDirectory();
});

it('refuses to create a composer project when Composer is missing, without touching the filesystem', function () {
    $parent = TemporaryProject::directory('workspace');
    $empty = TemporaryProject::directory('empty-bin');

    $result = Executable::run(
        ['new', 'my-site', '--composer', '--no-ansi', '--no-interaction'],
        $parent,
        ['PATH' => $empty]
    );

    expect($result['status'])->not->toBe(0)
        ->and($result['output'])
        ->toContain('Creating a Composer project requires Composer.')
        ->toContain('hyde new NAME --portable')
        ->and($parent.'/my-site')->not->toBeDirectory();
});

/*
|--------------------------------------------------------------------------
| Project detection in the wild
|--------------------------------------------------------------------------
*/

it('stays portable next to an unrelated composer manifest', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "# Home\n",
        'composer.json' => '{"name": "acme/thing", "description": "mentions hyde/framework", "require": {"monolog/monolog": "^3.0"}}',
    ]);

    $info = Executable::run(['info', '--no-ansi'], $path);

    expect($info['output'])->toContain('Project type: Portable');

    expect(Executable::run(['build', '--no-ansi'], $path)['status'])->toBe(0)
        ->and($path.'/vendor')->not->toBeDirectory();
});

it('fails loudly for a Hyde composer project with no dependencies installed', function () {
    $path = TemporaryProject::composer(withAutoloader: false);

    $result = Executable::run(['build', '--no-ansi'], $path);

    expect($result['status'])->not->toBe(0)
        ->and($result['output'])
        ->toContain('dependencies are not installed')
        ->toContain('composer install')
        ->and($path.'/_site')->not->toBeDirectory();
});

it('refuses to run a composer project whose entry point is not a PHP script', function () {
    $path = TemporaryProject::composer();

    // Someone copying the executable into their project must not silently get the
    // embedded framework building their Composer project.
    copy((string) Executable::path(), $path.'/hyde');

    $result = Executable::run(['build', '--no-ansi'], $path);

    expect($result['status'])->not->toBe(0)
        ->and($result['output'])->toContain('not a PHP script');
});
