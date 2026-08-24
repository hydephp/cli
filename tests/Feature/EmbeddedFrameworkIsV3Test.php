<?php

declare(strict_types=1);

use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| Proving the embedded framework is HydePHP v3
|--------------------------------------------------------------------------
|
| HydePHP v3 is unreleased. `HydeKernel::VERSION` now reads `3.0.0-dev`, but a
| version constant is a claim about the code rather than evidence of it, and for
| most of v3's development it read `2.0.3` on both lines and distinguished
| nothing at all.
|
| These tests therefore assert on behaviour that differs between the two lines.
| Each one fails against a CLI built from a published v2 package, which is what
| makes them evidence rather than documentation.
|
*/

it('does not register the rebuild command, which v3 removed', function () {
    $this->boot(TemporaryProject::portable());

    // The executable exposes whatever the embedded framework's console service provider
    // registers, so the absence of `rebuild` is a property of the framework it carries.
    expect(array_keys($this->registeredCommands()))->not->toContain('rebuild');
});

it('rejects the build options v3 removed', function () {
    $this->boot(TemporaryProject::portable());

    // v2 kept `--run-dev` and `--run-prod` as placeholders that produced a helpful error.
    // v3 dropped them, so Symfony rejects them as options the command does not define.
    $definition = $this->registeredCommands()['build']->getDefinition();

    expect($definition->hasOption('run-dev'))->toBeFalse()
        ->and($definition->hasOption('run-prod'))->toBeFalse()
        ->and($definition->hasOption('vite'))->toBeTrue();
});

it('labels a code block from the title modifier', function () {
    $path = TemporaryProject::portable(['_pages/index.md' => <<<'MD'
    # Index

    ```php title="app/Model.php"
    echo 'Hello World!';
    ```
    MD]);

    $this->boot($path);

    expect($this->runCommand('build'))->toBe(0);

    // v3 renders code blocks through a Blade view that puts the label in a `figcaption`
    // carrying the `hyde-code-block-label` hook. v2 had neither the view nor the syntax,
    // and would leave `title="app/Model.php"` in the info string as the language.
    expect(file_get_contents($path.'/_site/index.html'))
        ->toContain('hyde-code-block-label')
        ->toContain('app/Model.php');
});

it('leaves a filepath comment in the code, since v3 removed the syntax', function () {
    $path = TemporaryProject::portable(['_pages/index.md' => <<<'MD'
    # Index

    ```php
    // filepath: app/Legacy.php
    echo 'Hello World!';
    ```
    MD]);

    $this->boot($path);

    expect($this->runCommand('build'))->toBe(0);

    // v2 consumed this line and turned it into a label. v3 removed the processor, so the
    // comment stays in the rendered code as the ordinary first line it is.
    expect(file_get_contents($path.'/_site/index.html'))
        ->toContain('filepath: app/Legacy.php');
});

it('excludes draft and scheduled posts from the built site', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "# Index\n",
        '_posts/draft-post.md' => "---\ntitle: A Draft Post\ndraft: true\n---\n\nDraft body.\n",
        '_posts/future-post.md' => "---\ntitle: A Future Post\ndate: \"2099-01-01\"\n---\n\nFuture body.\n",
        '_posts/live-post.md' => "---\ntitle: A Live Post\ndate: \"2024-01-01\"\n---\n\nLive body.\n",
    ]);

    $this->boot($path);

    expect($this->runCommand('build'))->toBe(0);

    // Both publication states are v3 additions; v2 had no notion of either and would
    // have compiled all three posts.
    expect($path.'/_site/posts/live-post.html')->toBeFile()
        ->and($path.'/_site/posts/draft-post.html')->not->toBeFile()
        ->and($path.'/_site/posts/future-post.html')->not->toBeFile();
});

it('empties the whole output directory before building', function () {
    $path = TemporaryProject::portable();

    TemporaryProject::write($path, [
        '_site/stray.txt' => "left over from an earlier build\n",
        '_site/nested/stray.xml' => "<stray/>\n",
    ]);

    $this->boot($path);

    expect($this->runCommand('build'))->toBe(0);

    // v2 only removed HTML and JSON files along with the media directory, so anything
    // else in the output directory survived indefinitely. v3 treats the compiled site as
    // disposable build output and recreates it from source on every build.
    expect($path.'/_site/index.html')->toBeFile()
        ->and($path.'/_site/stray.txt')->not->toBeFile()
        ->and($path.'/_site/nested/stray.xml')->not->toBeFile();
});

it('copies _static files to the site root, which survives the emptying', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "# Index\n",
        '_static/CNAME' => "example.com\n",
        '_static/.well-known/probe.json' => "{}\n",
    ]);

    $this->boot($path);

    expect($this->runCommand('build'))->toBe(0);

    // `_static` is where v3 expects files that must be present in the compiled site, now
    // that the output directory is recreated from source on every build. Relative paths
    // are preserved, so a `.well-known` directory arrives intact.
    expect(file_get_contents($path.'/_site/CNAME'))->toContain('example.com')
        ->and($path.'/_site/.well-known/probe.json')->toBeFile();
});

it('reports a v3 framework version', function () {
    $this->boot(TemporaryProject::portable());

    expect($this->runCommand('info', ['--no-ansi' => true]))->toBe(0);

    // The version was ambiguous for most of v3's development, reading 2.0.3 on both
    // lines, and is now bumped. It is a claim rather than evidence — the checks above
    // are what establish the code is v3 — but a v2 framework could not make it.
    expect(Hyde\Foundation\HydeKernel::VERSION)->toStartWith('3.')
        ->and($this->consoleOutput())->toContain(Hyde\Foundation\HydeKernel::VERSION.' (embedded)');
});
