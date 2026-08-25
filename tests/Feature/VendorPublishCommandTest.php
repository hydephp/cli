<?php

declare(strict_types=1);

use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| Publishing framework assets into a portable project
|--------------------------------------------------------------------------
|
| The framework publishes its views and configuration from its own package
| directory. Inside the executable that directory is a path within the
| archive, which the command has to resolve for itself.
|
*/

it('publishes the framework configuration into a portable project', function () {
    $path = TemporaryProject::portable();

    $this->boot($path);

    expect($this->runCommand('publish:configs', ['--no-interaction' => true]))->toBe(0)
        ->and($path.'/config/hyde.php')->toBeFile();
});

it('publishes the homepage into a portable project', function () {
    $path = TemporaryProject::portable();

    $this->boot($path);

    expect($this->runCommand('publish:homepage', ['homepage' => 'posts', '--no-interaction' => true]))->toBe(0)
        ->and($path.'/_pages/index.blade.php')->toBeFile()
        ->and(file_get_contents($path.'/_pages/index.blade.php'))->toContain('Latest Posts');
});

it('builds a site that uses a published configuration', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "# Home\n",
        '_pages/site-name.blade.php' => '{{ config("hyde.name", "not-set") }}',
    ]);

    $this->boot($path);

    $this->runCommand('publish:configs', ['--no-interaction' => true]);

    // A portable project may override any framework configuration by publishing it.
    file_put_contents($path.'/config/hyde.php', str_replace(
        "'name' => env('SITE_NAME', 'HydePHP'),",
        "'name' => 'Published Config Name',",
        file_get_contents($path.'/config/hyde.php')
    ));

    $this->boot($path);

    expect($this->runCommand('build'))->toBe(0)
        ->and(file_get_contents($path.'/_site/site-name.html'))->toContain('Published Config Name');
});
