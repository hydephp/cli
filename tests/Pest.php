<?php

declare(strict_types=1);

use App\Launcher\Launcher;
use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Unit tests exercise the launcher and support classes on their own, with no
| application booted. Feature tests boot the embedded application against a
| real project directory. Integration tests run the native executable.
|
*/

uses(Tests\TestCase::class)->in('Unit', 'Feature', 'Integration');
uses(Tests\Support\BootsApplication::class)->in('Feature');

afterEach(function (): void {
    $this->shutdownApplication();
})->in('Feature');

/*
|--------------------------------------------------------------------------
| Cleanup
|--------------------------------------------------------------------------
|
| Every fixture project is created on disk outside the repository, and is
| removed again once the test that made it has finished.
|
*/

afterEach(function (): void {
    Launcher::swap(null);

    TemporaryProject::cleanup();
});

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/** Get a JSON fixture as an array from the general fixtures directory. */
function fixture(string $fixture): array
{
    return json_decode(file_get_contents(__DIR__.'/Fixtures/general/'.$fixture), true);
}
