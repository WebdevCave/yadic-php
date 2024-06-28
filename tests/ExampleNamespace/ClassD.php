<?php

namespace Webdevcave\Yadic\Tests\ExampleNamespace;

class ClassD
{
    use CountsInstancesTrait;

    public function __construct(
        int $x
    ) {
        static::increaseCounter();
    }
}
