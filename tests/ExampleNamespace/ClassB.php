<?php

namespace Webdevcave\Yadic\Tests\ExampleNamespace;

class ClassB
{
    use CountsInstancesTrait;

    public function __construct(
        public ClassA $a,
        public int $count = 2
    )
    {
        static::increaseCounter();
    }
}