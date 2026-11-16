<?php

namespace Webdevcave\Yadic\Tests\ExampleNamespace;

use Webdevcave\Yadic\Annotations\ArrayOf;

class Profile
{
    public function __construct(
        public Address|string|null $address = null,
        public Address|GeoPoint|null $location = null,
        public array|Address $extra = [],
        #[ArrayOf(Skill::class)]
        public array|Skill|null $skills = null,
        public Status|string|null $status = null,
        public ?Status $strictStatus = null,
        public $untyped = null,
        public int|string $code = 0,
    ) {
    }
}
