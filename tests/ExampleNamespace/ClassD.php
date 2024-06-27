<?php

namespace Webdevcave\Yadic\Tests\ExampleNamespace;

use Webdevcave\Yadic\Tests\ExampleNamespace\ClassA;
use Webdevcave\Yadic\Tests\ExampleNamespace\ClassB;
use Webdevcave\Yadic\Tests\ExampleNamespace\CountsInstancesTrait;

class ClassD
{
    use CountsInstancesTrait;

    public function __construct(
        int $x
    ) {
        static::increaseCounter();
    }
}
