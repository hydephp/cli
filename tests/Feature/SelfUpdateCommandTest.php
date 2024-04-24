<?php

use App\Commands\SelfUpdateCommand;
use Illuminate\Support\Facades\File;
use Illuminate\Console\BufferedConsoleOutput;
use Illuminate\Container\Container;
use Symfony\Component\Console\Input\ArrayInput;

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

/** Class that uses mocks instead of making real API and binary path calls */
class MockSelfUpdateCommand extends SelfUpdateCommand
{
    /** @var MockBufferedOutput */
    public $output;

    public function __construct()
    {
        parent::__construct();

        $this->input = Mockery::mock(ArrayInput::class, ['getOption' => false]);
        $this->output = new MockBufferedOutput();
    }
}

/** Buffered output that "interacts" with IO {@see \Illuminate\Console\Concerns\InteractsWithIO} */
class MockBufferedOutput extends BufferedConsoleOutput
{
    public function error($string, $verbosity = null): void
    {
        $this->line($string, 'error', $verbosity);
    }

    public function line($string, $style = null, $verbosity = null): void
    {
        $styled = $style ? "<$style>$string</$style>" : $string;

        $this->writeln($styled, $this->parseVerbosity($verbosity));
    }

    public function newLine(int $count = 1): void
    {
        $this->write(str_repeat(PHP_EOL, $count));
    }

    protected function parseVerbosity($level = null)
    {
        if (isset($this->verbosityMap[$level])) {
            $level = $this->verbosityMap[$level];
        } elseif (! is_int($level)) {
            $level = $this->getVerbosity();
        }

        return $level;
    }
}
