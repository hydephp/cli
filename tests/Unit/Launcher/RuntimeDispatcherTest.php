<?php

declare(strict_types=1);

use App\Launcher\RuntimeManager;
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

it('runs the bundled composer with the bundled runtime', function () {
    // An application root carrying a Composer but no PHP binary of its own: the runtime
    // then resolves to the CLI process already running, exactly as a source checkout
    // does, and the Composer is the real extraction path rather than a stand-in.
    $root = TemporaryProject::directory('bundled');

    mkdir($root.'/'.RuntimeManager::RUNTIME_DIRECTORY);

    $composer = '<?php exit($argv[1] === "install" && $argv[2] === "--no-dev" ? 6 : 1);';

    file_put_contents($root.'/'.RuntimeManager::RUNTIME_DIRECTORY.'/'.RuntimeManager::COMPOSER_FILE.RuntimeManager::RUNTIME_SUFFIX, gzencode($composer));

    file_put_contents($root.'/'.RuntimeManager::RUNTIME_DIRECTORY.'/'.RuntimeManager::MANIFEST_FILE, json_encode([
        'version' => PHP_VERSION,
        'checksum' => '',
        'composer' => ['version' => '2.8.12', 'filename' => RuntimeManager::COMPOSER_FILE, 'checksum' => hash('sha256', $composer)],
    ]));

    $dispatcher = new RuntimeDispatcher(new RuntimeManager(null, $root));

    expect($dispatcher->composer(['install', '--no-dev']))->toBe(6);
});

it('says so when asked for a composer the executable does not have', function () {
    $root = TemporaryProject::directory('bundled');

    mkdir($root.'/'.RuntimeManager::RUNTIME_DIRECTORY);

    // A complete runtime manifest, describing no Composer: a build made without one.
    file_put_contents($root.'/'.RuntimeManager::RUNTIME_DIRECTORY.'/'.RuntimeManager::MANIFEST_FILE, json_encode([
        'version' => PHP_VERSION,
        'checksum' => '',
    ]));

    expect(fn () => (new RuntimeDispatcher(new RuntimeManager(null, $root)))->composer(['install']))
        ->toThrow(LauncherException::class, 'does not bundle Composer');
});

/*
|--------------------------------------------------------------------------
| Composer updating itself
|--------------------------------------------------------------------------
|
| The bundled Composer is versioned with the executable. Letting it replace its
| own extracted copy would appear to work and then be undone by the next run,
| which verifies what it finds against the checksum from the build.
|
*/

it('refuses to let the bundled composer update itself', function (array $arguments) {
    $dispatcher = new class() extends RuntimeDispatcher
    {
        public bool $started = false;

        public function composer(array $arguments = []): int
        {
            $refused = $this->refuseSelfUpdate($arguments);

            if ($refused !== null) {
                return $refused;
            }

            $this->started = true;

            return 0;
        }
    };

    // The status is what a script acts on, and it is not success. The explanation goes
    // to standard error, where it cannot be mistaken for the output of a command.
    expect($dispatcher->composer($arguments))->toBe(1)
        ->and($dispatcher->started)->toBeFalse();
})->with([
    'the command' => [['self-update']],
    'its alias' => [['selfupdate']],
    'behind an option' => [['--no-ansi', 'self-update']],
    'with its own options' => [['self-update', '--rollback']],
]);

it('lets every other composer command through', function (array $arguments) {
    $dispatcher = new class() extends RuntimeDispatcher
    {
        public function refusal(array $arguments): ?int
        {
            return $this->refuseSelfUpdate($arguments);
        }
    };

    expect($dispatcher->refusal($arguments))->toBeNull();
})->with([
    'install' => [['install']],
    'update' => [['update']],
    'nothing at all' => [[]],
    'only options' => [['--version']],
    'a package that reads like it' => [['require', 'acme/self-update']],
    'an option that took a separate value' => [['-d', '/some/path', 'install']],
]);

it('refuses a program it does not bundle', function () {
    expect(fn () => (new RuntimeDispatcher())->run('perl'))
        ->toThrow(LauncherException::class, 'bundles no `perl` program');
});
