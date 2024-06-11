<?php

test('config file has the correct structure', function () {
    $config = require __DIR__.'/../../app/config.php';

    expect($config)->toHaveKeys(['name', 'version', 'env', 'providers', 'aliases']);
});
