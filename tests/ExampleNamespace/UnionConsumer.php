<?php

namespace Webdevcave\Yadic\Tests\ExampleNamespace;

use Webdevcave\Yadic\Annotations\Inject;

class UnionConsumer
{
    public function __construct(
        public ClassD|ClassA $dependency,
        public ClassD|int $number = 3,
        public ClassD|string|null $optional = null,
        #[Inject('testAlias')]
        public ?InterfaceA $aliased = null,
    ) {
    }
}
