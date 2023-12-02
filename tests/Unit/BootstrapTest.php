<?php

use Hyde\Foundation\Application;

$app = require __DIR__ . '/../../app/bootstrap.php';

test('bootstrapper returns application', function () use ($app) {
    expect($app)->toBeInstanceOf(Application::class);
});
