<?php

declare(strict_types=1);

use App\Commands\InfoCommand;
use App\Commands\ServeCommand;
use App\Commands\NewProjectCommand;
use App\Commands\SelfUpdateCommand;
use Tests\Support\TemporaryProject;

it('registers the commands the executable owns', function () {
    $this->boot(TemporaryProject::portable());

    $commands = $this->registeredCommands();

    expect($commands)->toHaveKeys(['info', 'new', 'self-update', 'serve'])
        ->and($commands['info'])->toBeInstanceOf(InfoCommand::class)
        ->and($commands['new'])->toBeInstanceOf(NewProjectCommand::class)
        ->and($commands['self-update'])->toBeInstanceOf(SelfUpdateCommand::class);
});

it('overrides the realtime compiler serve command with its own', function () {
    $this->boot(TemporaryProject::portable());

    $commands = $this->registeredCommands();

    // The bundled serve command starts the server with the runtime inside the executable,
    // which the realtime compiler's own command knows nothing about.
    expect($commands['serve'])->toBeInstanceOf(ServeCommand::class);
});

it('registers the framework commands a portable project needs', function () {
    $this->boot(TemporaryProject::portable());

    $commands = $this->registeredCommands();

    expect($commands)->toHaveKeys(['build', 'route:list', 'make:page', 'make:post']);
});

it('binds the detected project and the runtime manager', function () {
    $path = TemporaryProject::portable();

    $this->boot($path);

    expect($this->app->make(App\Launcher\Project::class)->root)->toBe($path)
        ->and($this->app->make(App\Launcher\RuntimeManager::class))->toBeInstanceOf(App\Launcher\RuntimeManager::class);
});

it('does not expose the internal build tooling as commands', function () {
    $this->boot(TemporaryProject::portable());

    $commands = array_keys($this->registeredCommands());

    expect($commands)->not->toContain('standalone:build')
        ->and($commands)->not->toContain('build:phar');
});
