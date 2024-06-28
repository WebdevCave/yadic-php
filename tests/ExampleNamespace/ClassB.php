<?php

namespace Webdevcave\Yadic\Tests\ExampleNamespace;

use Webdevcave\Yadic\Annotations\Inject;

class ClassB
{
    use CountsInstancesTrait;

    public function __construct(
        #[Inject] public ClassA $a,
        public int $count = 2
    )
    {
        static::increaseCounter();
    }
}
