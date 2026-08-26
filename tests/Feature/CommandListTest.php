<?php

declare(strict_types=1);

use App\Launcher\Launcher;
use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| hyde list
|--------------------------------------------------------------------------
|
| The list is the only place most people ever read about what the executable
| can do, so it has to say which commands belong to the CLI itself and which
| belong to the project. The two are not interchangeable, and the section a
| command is in is read from the launcher's routing rather than restated.
|
*/

/** @return array{0: string, 1: string} The CLI section, and everything after it. */
function listSections(string $output): array
{
    $output = preg_replace('/\e\[[\d;]*m/', '', $output) ?? $output;
    [$before, $after] = explode("\n  Hyde project\n", $output, 2);

    return [explode("\n  HydeCLI\n", $before, 2)[1] ?? '', $after];
}

beforeEach(function () {
    $this->boot(TemporaryProject::portable());

    expect($this->runCommand('list'))->toBe(0);

    $this->rendered = $this->consoleOutput();
    $this->plain = preg_replace('/\e\[[\d;]*m/', '', $this->rendered) ?? $this->rendered;
});

it('gives the commands the executable owns a section of their own', function () {
    expect($this->rendered)
        ->toContain('HydeCLI')
        ->toContain('Works from any directory')
        ->toContain('Hyde project')
        ->toContain('Commands available in the current Hyde project')
        ->not->toContain('HYDE CLI');
});

it('lists every command the launcher answers in that section', function () {
    [$cli] = listSections($this->rendered);

    foreach (Launcher::ownedCommands() as $command) {
        expect($cli)->toContain($command);
    }
});

it('lists the programs the executable bundles with the commands it owns', function () {
    [$cli] = listSections($this->rendered);

    expect($cli)
        ->toContain('Run the bundled PHP CLI.')
        ->toContain('Run the bundled Composer.');
});

it('leaves the project commands out of the CLI section', function () {
    [$cli, $project] = listSections($this->rendered);

    expect($cli)->not->toContain('build')
        ->and($project)->toContain('build')
        ->and($project)->toContain('make:page')
        ->and($project)->toContain('route:list');
});

it('renders project command subgroups', function () {
    expect($this->plain)
        ->toContain('Core')
        ->toContain('Build')
        ->toContain('Create')
        ->toContain('Publish')
        ->toContain('Other')
        ->toMatch('/^    Build\n      .*build:rss/m')
        ->toMatch('/^    Create\n      .*make:page/m')
        ->toMatch('/^    Publish\n(?s:.*?)      publish:views/m')
        ->toMatch('/^    Other\n      herd:install/m')
        ->toMatch('/^    Other\n(?s:.*?)      route:list/m');
});

it('aligns descriptions across all project command groups', function () {
    $lines = array_values(array_filter(
        explode("\n", $this->plain),
        fn (string $line): bool => preg_match('/^      (build|make|publish|herd|route):/', $line) === 1,
    ));

    $descriptionColumns = array_map(
        function (string $line): int {
            preg_match('/^\s+\S+ +\S/', $line, $match);

            return strlen($match[0]) - 1;
        },
        $lines,
    );

    expect($descriptionColumns)->not->toBeEmpty()
        ->and(array_unique($descriptionColumns))->toHaveCount(1);
});

it('puts the command that creates a project first', function () {
    // `new` creates the project the rest of the list acts on, so it leads.
    [$cli] = listSections($this->rendered);

    expect(strpos($cli, 'new'))->toBeLessThan(strpos($cli, 'self-update'));
});

it('names the program in the usage line', function () {
    expect($this->rendered)->toContain('USAGE: '.ARTISAN_BINARY.' <command>');
});

it('does not list the list command itself', function () {
    // It is the command being run, and the configuration hides it. `route:list` is
    // matched by the same words, so this asks about the start of a line.
    expect($this->rendered)->not->toMatch('/^  list\s/m');
});
