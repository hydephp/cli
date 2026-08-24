<?php

declare(strict_types=1);

use Hyde\Foundation\HydeKernel;
use Tests\Support\TemporaryProject;


/*
|--------------------------------------------------------------------------
| hyde info
|--------------------------------------------------------------------------
|
| The command has to answer, without booting anybody else's dependency graph,
| which kind of project this is and where its framework comes from.
|
*/

it('reports a portable project and the embedded framework', function () {
    $path = TemporaryProject::portable();

    $this->boot($path);

    expect($this->runCommand('info'))->toBe(0);

    expect($this->consoleOutput())
        ->toContain('Hyde CLI:')
        ->toContain('3.0.0')
        ->toContain('Project type: Portable')
        ->toContain('Framework:')
        ->toContain(HydeKernel::VERSION.' (embedded)')
        ->toContain('PHP:')
        ->toContain(PHP_VERSION)
        ->toContain('Root:')
        ->toContain($path);
});

it('does not mention a dependency source for a portable project', function () {
    $this->boot(TemporaryProject::portable());

    $this->runCommand('info');

    expect($this->consoleOutput())->not->toContain('Dependencies:');
});

it('reports a composer project and the version its lock file pins', function () {
    $path = TemporaryProject::composer([
        'composer.lock' => json_encode(['packages' => [['name' => 'hyde/framework', 'version' => 'v9.9.9']]]),
    ]);

    $this->boot($path);

    $this->runCommand('info');

    expect($this->consoleOutput())
        ->toContain('Project type: Composer')
        ->toContain('9.9.9 (project)')
        ->toContain('Dependencies: ./vendor/autoload.php')
        ->toContain($path);
});

it('falls back to the declared constraint when there is no lock file', function () {
    $this->boot(TemporaryProject::composer());

    $this->runCommand('info');

    expect($this->consoleOutput())->toContain('^2.0 (project)');
});

it('reports whether the PHP runtime is bundled', function () {
    $this->boot(TemporaryProject::portable());

    $this->runCommand('info');

    // Running from a source checkout, the interpreter is the system one; the built
    // executable reports `bundled` instead, which the integration suite asserts.
    expect($this->consoleOutput())->toContain(PHP_SAPI === 'micro' ? '(bundled)' : '(system)');
});

it('lists the platform and required extensions when verbose', function () {
    $this->boot(TemporaryProject::portable());

    $this->console->setVerbosity(Symfony\Component\Console\Output\OutputInterface::VERBOSITY_VERBOSE);

    $this->runCommand('info', ['-v' => true]);

    expect($this->consoleOutput())
        ->toContain('Platform:')
        ->toContain('SAPI:')
        ->toContain('Extensions:')
        ->toContain('mbstring');
});
