<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| Verify the embedded dependency graph is HydePHP v3
|--------------------------------------------------------------------------
|
| HydePHP v3 is unreleased: there is no 3.x tag on Packagist, and the version
| numbers on the development branch have deliberately not been bumped, so
| `HydeKernel::VERSION` still reads `2.0.3` on both lines. The generation of
| the embedded framework therefore cannot be read off a version string.
|
| This checks the two things that do distinguish the lines: where the package
| came from, and whether the code carries v3's removals and additions. It is
| run by the build before the executable is assembled, so that resolving to a
| published v2 package breaks the build instead of shipping quietly.
|
*/

$root = dirname(__DIR__);

$failures = [];

$fail = static function (string $message) use (&$failures): void {
    $failures[] = $message;
};

$lock = json_decode((string) file_get_contents("$root/composer.lock"), true);

if (! is_array($lock)) {
    fwrite(STDERR, "Could not read composer.lock\n");
    exit(1);
}

$packages = [];

foreach ($lock['packages'] ?? [] as $package) {
    $packages[$package['name']] = $package;
}

// The development packages must come from the local develop@master checkout. A `path`
// dist type is what proves the monorepo source won the resolution, since a published
// package would carry a `zip` dist pointing at a GitHub zipball instead.
foreach (['hyde/framework', 'hyde/realtime-compiler'] as $name) {
    if (! isset($packages[$name])) {
        $fail("$name is not installed");

        continue;
    }

    $package = $packages[$name];
    $type = $package['dist']['type'] ?? null;
    $version = $package['version'] ?? '?';

    if ($type !== 'path') {
        $fail("$name resolved to a published package ($version, dist type '$type') rather than the develop@master checkout");
    }

    if ($version !== 'dev-master') {
        $fail("$name is at '$version', expected 'dev-master'");
    }
}

$framework = "$root/vendor/hyde/framework";

if (! is_dir($framework)) {
    $fail('hyde/framework is not present in vendor');
}

// v3 removed the rebuild command outright, along with the filepath comment processor
// that the title modifier replaced. Their presence means a v2 framework is installed.
foreach ([
    'src/Console/Commands/RebuildPageCommand.php' => 'the rebuild command, removed in v3',
    'src/Markdown/Processing/CodeblockFilepathProcessor.php' => 'the filepath comment processor, removed in v3',
    'src/Framework/Actions/PostBuildTasks/GenerateSitemap.php' => 'the GenerateSitemap post-build task, removed in v3',
    'src/Framework/Actions/PostBuildTasks/GenerateRssFeed.php' => 'the GenerateRssFeed post-build task, removed in v3',
] as $path => $what) {
    if (file_exists("$framework/$path")) {
        $fail("The installed framework still carries $what ($path)");
    }
}

// And these arrived with v3.
foreach ([
    'src/Markdown/Processing/BladeBlockProcessor.php' => 'Blade Blocks',
    'src/Markdown/Extensions/CodeBlockViewModel.php' => 'composable code blocks',
    'src/Markdown/Extensions/TerminalBlockViewModel.php' => 'terminal code blocks',
] as $path => $what) {
    if (! file_exists("$framework/$path")) {
        $fail("The installed framework is missing $what ($path), which v3 added");
    }
}

$provider = "$framework/src/Console/ConsoleServiceProvider.php";

if (is_file($provider) && str_contains((string) file_get_contents($provider), 'RebuildPageCommand')) {
    $fail('The console service provider still registers RebuildPageCommand');
}

if ($failures !== []) {
    fwrite(STDERR, "\n  The embedded dependency graph is not HydePHP v3.\n\n");

    foreach ($failures as $failure) {
        fwrite(STDERR, "  - $failure\n");
    }

    fwrite(STDERR, "\n  Run `bin/sync-develop.sh` and `composer update hyde/framework hyde/realtime-compiler`.\n\n");

    exit(1);
}

fwrite(STDOUT, "The embedded dependency graph is HydePHP v3 (develop@master).\n");
fwrite(STDOUT, "  Pre-release scaffolding: when v3 is tagged, drop the path repositories for ^3.0\n");
fwrite(STDOUT, "  and delete this script, bin/sync-develop.sh and tests/Fixtures/project-template.\n");
