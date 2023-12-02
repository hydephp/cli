<?php

$app = require __DIR__ . '/../../app/bootstrap.php';

test('bootstrapper returns application', function () use ($app) {
    expect($app)->toBeInstanceOf(\Hyde\Foundation\Application::class);
});
