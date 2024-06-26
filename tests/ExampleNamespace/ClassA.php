<?php

namespace Webdevcave\Yadic\Tests\ExampleNamespace;

use Webdevcave\Yadic\Annotations\Provides;
use Webdevcave\Yadic\Annotations\Singleton;

#[Singleton]
#[Provides(InterfaceA::class)]
#[Provides('testAlias')]
class ClassA implements InterfaceA
{
    use CountsInstancesTrait;

    public function __construct()
    {
        static::increaseCounter();
    }

    public function funcWithoutParameters(): bool
    {
        return true;
    }

    public function funcWithDefaultParameter(int $x = 1): bool
    {
        return true;
    }

    public function funcWithParameters(int $x, ClassC $c): bool
    {
        return true;
    }
}
