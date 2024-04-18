<?php

use App\Commands\SelfUpdateCommand;

it('parses the version correctly', function ($input, $expectedOutput) {
    $class = new InspectableSelfUpdateCommand();

    $result = $class->parseVersion($input);

    expect($result)->toBe($expectedOutput);
})->with([
    ['1.2.3', ['major' => 1, 'minor' => 2, 'patch' => 3]],
    ['2.5.0', ['major' => 2, 'minor' => 5, 'patch' => 0]],
    ['0.0.1', ['major' => 0, 'minor' => 0, 'patch' => 1]],
]);

class InspectableSelfUpdateCommand extends SelfUpdateCommand
{
    public function property(string $property): mixed
    {
        return $this->$property;
    }

    public function __call($method, $parameters)
    {
        return $this->$method(...$parameters);
    }
}
