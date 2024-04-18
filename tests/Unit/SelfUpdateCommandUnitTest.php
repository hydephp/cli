<?php

use App\Commands\SelfUpdateCommand;

$versions = [
    ['1.2.3', ['major' => 1, 'minor' => 2, 'patch' => 3]],
    ['2.5.0', ['major' => 2, 'minor' => 5, 'patch' => 0]],
    ['0.0.1', ['major' => 0, 'minor' => 0, 'patch' => 1]],
];

it('parses the version correctly', function ($input, $expectedOutput) {
    expect((new InspectableSelfUpdateCommand())->parseVersion($input))->toBe($expectedOutput);
})->with($versions);

it('returns an array with integer values', function ($input, $expectedOutput) {
    $result = (new InspectableSelfUpdateCommand())->parseVersion($input);

    expect($result)->toEqual($expectedOutput)
        ->and($result['major'])->toBeInt()
        ->and($result['minor'])->toBeInt()
        ->and($result['patch'])->toBeInt();
})->with($versions);

it('correctly compares versions', function ($currentVersion, $latestVersion, $expectedResult) {
    $class = new InspectableSelfUpdateCommand();

    $result = $class->compareVersions($class->parseVersion($currentVersion), $class->parseVersion($latestVersion));

    expect($result)->toBe($class->constants($expectedResult));
})->with([
    ['1.2.3', '1.2.3', 'STATE_UP_TO_DATE'],
    ['1.2.3', '2.0.0', 'STATE_BEHIND'],
    ['2.0.0', '1.2.3', 'STATE_AHEAD'],
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

    public function constants(string $constant): mixed
    {
        return constant("self::$constant");
    }
}
