<?php

use App\Commands\SelfUpdateCommand;

class InspectableSelfUpdateCommand extends SelfUpdateCommand
{
    public function property(string $property): mixed
    {
        return $this->$property;
    }

    public function method(string $command, array $arguments = []): mixed
    {
        return $this->$command(...$arguments);
    }
}
