<?php

use App\Commands\SelfUpdateCommand;

it('parses the version correctly', function () {
    $class = new InspectableSelfUpdateCommand();

    $testCases = [
        ['1.2.3', ['major' => 1, 'minor' => 2, 'patch' => 3]],
        ['2.5.0', ['major' => 2, 'minor' => 5, 'patch' => 0]],
        ['0.0.1', ['major' => 0, 'minor' => 0, 'patch' => 1]],
    ];

    foreach ($testCases as [$input, $expectedOutput]) {
        $result = $class->method('parseVersion', $input);

        expect($result)->toBe($expectedOutput);
    }
});

class InspectableSelfUpdateCommand extends SelfUpdateCommand
{
    public function property(string $property): mixed
    {
        return $this->$property;
    }

    public function method(string $command, mixed ...$arguments): mixed
    {
        return $this->$command(...$arguments);
    }
}
