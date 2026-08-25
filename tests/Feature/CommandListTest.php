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
    [$before, $after] = explode('PROJECT', $output, 2);

    return [explode('HYDE CLI', $before, 2)[1] ?? '', $after];
}

beforeEach(function () {
    $this->boot(TemporaryProject::portable());

    expect($this->runCommand('list'))->toBe(0);

    $this->rendered = $this->consoleOutput();
});

it('gives the commands the executable owns a section of their own', function () {
    expect($this->rendered)
        ->toContain('HYDE CLI')
        ->toContain('the executable itself, in any directory')
        ->toContain('PROJECT')
        ->toContain('the Hyde site in the current directory');
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
