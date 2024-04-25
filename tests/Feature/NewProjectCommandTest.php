<?php

use App\Application;
use App\Commands\NewProjectCommand;
use Illuminate\Console\OutputStyle;
use Illuminate\Process\Factory;
use Illuminate\Support\Facades\Process;
use Symfony\Component\Console\Formatter\OutputFormatterInterface;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

test('can create new project', function () {
    Process::swap(new Factory());

    Process::preventStrayProcesses();

    Process::shouldReceive('command')->once()->with('composer create-project hyde/hyde test-project --prefer-dist --ansi')->andReturnSelf();

    Process::shouldReceive('run')->once()->withArgs([null, Mockery::type(Closure::class)])->andReturnSelf();

    $command = configureMocks(new NewProjectCommand());

    $command->handle();
});

function configureMocks(NewProjectCommand $command): NewProjectCommand
{
    $app = Mockery::mock(Application::class)->makePartial();
    $input = Mockery::mock(InputInterface::class);
    $input->makePartial();
    $input->shouldReceive('getOption')->andReturn(false);
    $input->shouldReceive('getArgument')->andReturn('test-project');
    $formatter = Mockery::mock(OutputFormatterInterface::class);
    $formatter->shouldReceive('setDecorated');
    $outputInterface = Mockery::mock(OutputInterface::class);
    $outputInterface->shouldReceive('getVerbosity')->andReturn(0);
    $outputInterface->shouldReceive('write');
    $outputInterface->shouldReceive('writeln');
    $outputInterface->shouldReceive('getFormatter')->andReturn($formatter);

    $output = Mockery::mock(OutputStyle::class, [$input, $outputInterface])->makePartial();

    $command->setLaravel($app);
    $command->setOutput($output);
    $command->setInput($input);

    return $command;
}
