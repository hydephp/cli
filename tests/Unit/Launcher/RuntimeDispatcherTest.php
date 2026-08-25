<?php

declare(strict_types=1);

use App\Launcher\LauncherException;
use App\Launcher\RuntimeDispatcher;
use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| Running the programs the executable carries
|--------------------------------------------------------------------------
|
| These commands are a passthrough and nothing else: what the user typed is what
| the program gets, and what the program returned is what the shell gets.
|
*/

it('runs the bundled PHP runtime', function () {
    $status = (new RuntimeDispatcher())->php(['-r', 'exit(0);']);

    expect($status)->toBe(0);
});

it('returns the exit status the runtime returned', function () {
    expect((new RuntimeDispatcher())->php(['-r', 'exit(9);']))->toBe(9);
});

it('forwards the arguments to the runtime untouched', function () {
    $directory = TemporaryProject::directory('runtime');

    file_put_contents($file = $directory.'/arguments.php', '<?php file_put_contents($argv[1], implode("|", array_slice($argv, 2)));');

    (new RuntimeDispatcher())->php([$file, $written = $directory.'/arguments.txt', '--flag', 'a b', '-v']);

    expect(file_get_contents($written))->toBe('--flag|a b|-v');
});

it('runs the runtime through the `php` command name', function () {
    expect((new RuntimeDispatcher())->run('php', ['-r', 'exit(5);']))->toBe(5);
});

it('refuses a program it does not bundle', function () {
    expect(fn () => (new RuntimeDispatcher())->run('perl'))
        ->toThrow(LauncherException::class, 'bundles no `perl` program');
});
