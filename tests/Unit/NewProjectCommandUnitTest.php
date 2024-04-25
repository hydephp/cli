<?php

use App\Commands\NewProjectCommand;
use Symfony\Component\Console\Output\OutputInterface;

test('bufferedOutput method', function () {
    $command = new NewProjectTestCommand();

    $output = Mockery::mock(OutputInterface::class);
    $output->shouldReceive('write')->once()->with('buffer');
    $command->mockOutput($output);

    $bufferedOutput = $command->bufferedOutput();

    $bufferedOutput('type', 'buffer');
});

test('getLogo method without name trims logo', function () {
    $command = new NewProjectTestCommand();

    $command->mockArgument('name', null);
    $logo = $command->getLogo();

    $this->assertStringNotContainsString("\n\n", $logo);
});

test('getLogo method with name does not trim logo', function () {
    $command = new NewProjectTestCommand();

    $command->mockArgument('name', 'test-project');
    $logo = $command->getLogo();

    $this->assertStringContainsString("\n\n", $logo);
});

class NewProjectTestCommand extends NewProjectCommand
{
    protected array $arguments;

    public function mockOutput($output): void
    {
        $this->output = $output;
    }

    public function mockArgument($key, $value): void
    {
        $this->arguments[$key] = $value;
    }

    public function argument($key = null): ?string
    {
        return $this->arguments[$key];
    }

    public function bufferedOutput(): Closure
    {
        return parent::bufferedOutput();
    }

    public function getLogo(): string
    {
        return parent::getLogo();
    }
}
