<?php

test('hyde runs', function () {
    $hyde = __DIR__ . '/../../hyde';

    $output = shell_exec("php $hyde");

    expect($output)->toContain('Experimental Standalone');
});
