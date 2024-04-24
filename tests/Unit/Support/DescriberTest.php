<?php

use Illuminate\Console\Command;
use App\Commands\Internal\Describer;

it('sorts the commands properly', function () {
    $commands = createCommandMocks(['aaa', 'new', 'bbb']);

    TestDescriber::sortCommandsInGroup($commands);

    $this->assertSame(['new', 'aaa', 'bbb'], commandNames($commands));
});

it('sorts the commands properly with different starting order', function () {
    $commands = createCommandMocks(['new', 'aaa', 'bbb']);

    TestDescriber::sortCommandsInGroup($commands);

    $this->assertSame(['new', 'aaa', 'bbb'], commandNames($commands));
});

function createCommandMocks(array $names): array
{
    return array_map(fn (string $name): Command => tap(
        test()->getMockBuilder(Command::class)->disableOriginalConstructor()->getMock(),
        fn ($command) => $command->method('getName')->willReturn($name)
    ), $names);
}

function commandNames(array $commands): array
{
    return array_map(fn (Command $command):   string => $command->getName(), $commands);
}

class TestDescriber extends Describer
{
    public static function sortCommandsInGroup(array &$commands): void
    {
        parent::sortCommandsInGroup($commands);
    }
}
