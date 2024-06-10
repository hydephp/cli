<?php

namespace Tests;

use PHPUnit\Framework\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        putenv('HYDE_WORKING_DIR=./path/to/working/dir');
        putenv('HYDE_TEMP_DIR=./path/to/temp/dir');
    }
}
