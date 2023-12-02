<?php /** @noinspection PhpIllegalPsrClassPathInspection */

use App\Commands\PharServeCommand;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

class TestablePharServeCommand extends PharServeCommand
{
    public function __construct()
    {
        parent::__construct();

        $this->input = tap(Mockery::mock(ArrayInput::class), function ($mock) {
            $mock->shouldReceive('getOption')->with('no-ansi')->andReturn('false');
            $mock->shouldReceive('getOption')->with('host')->andReturn('localhost');
            $mock->shouldReceive('getOption')->with('port')->andReturn(8080);
            $mock->shouldReceive('getOption')->with('save-preview')->andReturnNull();
            $mock->shouldReceive('getOption')->with('dashboard')->andReturnNull();
            $mock->shouldReceive('getOption')->with('pretty-urls')->andReturnNull();
            $mock->shouldReceive('getOption')->with('play-cdn')->andReturnNull();
        });
        $this->output = new BufferedOutput();
    }

    public function getEnvironmentVariables(): array
    {
        return parent::getEnvironmentVariables();
    }
}
