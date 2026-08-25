<?php

declare(strict_types=1);

use App\Launcher\Subprocess;

/*
|--------------------------------------------------------------------------
| The plumbing shared by everything the launcher starts
|--------------------------------------------------------------------------
|
| The launcher starts other programs in two places: a Composer project, and the
| bundled runtime commands. Both go through here, so what this class does is
| what both of them do.
|
*/

it('runs a program and returns its exit status', function () {
    expect(Subprocess::run([PHP_BINARY, '-r', 'exit(0);']))->toBe(0)
        ->and(Subprocess::run([PHP_BINARY, '-r', 'exit(3);']))->toBe(3);
});

it('runs the program in the given working directory', function () {
    $directory = realpath(sys_get_temp_dir());

    $status = Subprocess::run([PHP_BINARY, '-r', 'exit(realpath(getcwd()) === realpath($argv[1]) ? 0 : 1);', $directory], $directory);

    expect($status)->toBe(0);
});

it('gives the program the environment it was handed', function () {
    $status = Subprocess::run([PHP_BINARY, '-r', 'exit(getenv("HYDE_SUBPROCESS_PROBE") === "set" ? 0 : 1);'], null, ['HYDE_SUBPROCESS_PROBE' => 'set']);

    expect($status)->toBe(0);
});

/*
|--------------------------------------------------------------------------
| The search path
|--------------------------------------------------------------------------
*/

it('puts the bundled runtime at the front of the search path', function () {
    $environment = Subprocess::environmentWith('/opt/hyde/runtime/php');

    expect($environment[Subprocess::searchPathKey($environment)])
        ->toStartWith('/opt/hyde/runtime'.PATH_SEPARATOR);
});

it('finds the search path key whatever case the platform used', function () {
    expect(Subprocess::searchPathKey(['Path' => 'C:\\Windows', 'HOME' => 'C:\\Users\\emma']))->toBe('Path')
        ->and(Subprocess::searchPathKey(['PATH' => '/usr/bin']))->toBe('PATH')
        ->and(Subprocess::searchPathKey(['path' => '/usr/bin']))->toBe('path')
        ->and(Subprocess::searchPathKey(['HOME' => '/home/emma']))->toBe('PATH');
});
