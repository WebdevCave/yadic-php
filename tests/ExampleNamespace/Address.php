<?php

namespace Webdevcave\Yadic\Tests\ExampleNamespace;

class Address
{
    public function __construct(
        public string $street,
        public ?string $city = null,
    ) {
    }
}
