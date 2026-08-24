<?php

declare(strict_types=1);

use Tests\Support\Server;
use Tests\Support\Executable;
use Tests\Support\TemporaryProject;

/*
|--------------------------------------------------------------------------
| hyde serve, without a PHP on the machine
|--------------------------------------------------------------------------
|
| The executable is a micro SAPI and has no built-in web server, so serving is
| the one command that genuinely needs a CLI runtime. These tests prove it
| gets one from inside the executable, and that a real request is answered.
|
*/

beforeEach(function () {
    if (Executable::path() === null) {
        $this->markTestSkipped(Executable::missingMessage());
    }

    $this->server = null;
});

afterEach(function () {
    $this->server?->stop();
});

it('serves a portable project over HTTP with no PHP available', function () {
    $path = TemporaryProject::portable([
        '_pages/index.md' => "---\ntitle: Served Page\n---\n\n# Hello From The Server\n",
        'hyde.yml' => "name: \"Served Site\"\n",
    ]);

    $this->server = Server::start($path);

    expect($this->server->waitUntilReady())->toBeTrue($this->server->output());

    $response = $this->server->get('/');

    expect($response)
        ->toContain('Hello From The Server')
        ->toContain('Served Site');
});

it('starts the server with the bundled runtime rather than a system PHP', function () {
    $this->server = Server::start(TemporaryProject::portable());

    expect($this->server->waitUntilReady())->toBeTrue($this->server->output());

    $this->server->get('/');

    // The built-in server announces the version it is running, which is the one the
    // executable carries, extracted into the cache directory it was given.
    expect($this->server->output())
        ->toContain('Development Server')
        ->toContain('http://'.$this->server->host.':'.$this->server->port);

    expect(glob(Executable::home().'/.cache/hyde/runtime/*/*/php'))->not->toBeEmpty();
});

it('serves the realtime compiler dashboard', function () {
    $this->server = Server::start(TemporaryProject::portable());

    expect($this->server->waitUntilReady())->toBeTrue($this->server->output());

    expect($this->server->get('/dashboard'))->toContain('Dashboard');
});

it('compiles a page that was added after the server started', function () {
    $path = TemporaryProject::portable();

    $this->server = Server::start($path);

    expect($this->server->waitUntilReady())->toBeTrue($this->server->output());

    TemporaryProject::write($path, ['_pages/added-later.md' => "---\ntitle: Added Later\n---\n\n# Compiled On Request\n"]);

    expect($this->server->get('/added-later.html'))->toContain('Compiled On Request');
});
