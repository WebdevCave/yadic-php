<?php

namespace Webdevcave\Yadic\Tests\ExampleNamespace;

class GeoPoint
{
    public function __construct(
        public float $lat,
        public float $lng,
    ) {
    }
}
