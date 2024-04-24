<?php

use Illuminate\Console\Command;
use App\Commands\Internal\Describer;

test('test sort commands in group method', function () {
    // Mock the Command class for testing
    $command1 = createCommandMock('aaa');
    $command2 = createCommandMock('new');
    $command3 = createCommandMock('bbb');

    // Call the method to be tested
    $commands = [$command1, $command2, $command3];
    TestDescriber::sortCommandsInGroup($commands);

    // Assert that the commands are sorted correctly
    $this->assertSame('new', $commands[0]->getName());
    $this->assertSame('aaa', $commands[1]->getName());
    $this->assertSame('bbb', $commands[2]->getName());

    $this->assertSame(['new', 'aaa', 'bbb'], array_map(fn ($command) => $command->getName(), $commands));
});

function createCommandMock(string $name): Command
{
    $command = test()->getMockBuilder(Command::class)
        ->disableOriginalConstructor()
        ->getMock();

    $command->method('getName')->willReturn($name);

    return $command;
}

class TestDescriber extends Describer
{
    public static function sortCommandsInGroup(array &$commands): void
    {
        parent::sortCommandsInGroup($commands);
    }
}
