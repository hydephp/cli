<?php

use Illuminate\Support\Facades\File;
use Illuminate\Container\Container;

// We want to run everything in a clean temporary directory
$path = __DIR__.'/../../vendor/.testing';

beforeAll(function () use ($path) {
    if (is_dir($path)) {
        throw new RuntimeException('The directory already exists. Please remove it first.');
    } else {
        mkdir($path, 0777, true);
    }

    $mock = Mockery::mock(Container::class);
    $mock->shouldReceive('basePath')->andReturn($path);
    Container::setInstance($mock);
});

afterAll(function () use ($path) {
    // Clean up the temporary directory
    File::deleteDirectory($path);
});
