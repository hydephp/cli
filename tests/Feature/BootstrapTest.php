<?php

test('bootstrapper returns application', function () {
    $app = require __DIR__ . '/../../app/bootstrap.php';

    expect($app)->toBeInstanceOf(\Hyde\Foundation\Application::class);
});

test('anonymous bootstrapper returns application', function () {
    $app = require __DIR__ . '/../../app/anonymous-bootstrap.php';

    expect($app)->toBeInstanceOf(\Hyde\Foundation\Application::class);
});
