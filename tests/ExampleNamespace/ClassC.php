<?php

namespace Webdevcave\Yadic\Tests\ExampleNamespace;

class ClassC
{
    use CountsInstancesTrait;

    public function __construct(
        public ClassA $a,
        public ClassB $b
    )
    {
        static::increaseCounter();
    }
}
