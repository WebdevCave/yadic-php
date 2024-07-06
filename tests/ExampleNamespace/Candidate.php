<?php

namespace Webdevcave\Yadic\Tests\ExampleNamespace;

use Webdevcave\Yadic\Annotations\ArrayOf;

class Candidate
{
    public function __construct(
        public ?string $name = null,
        public ?int $age = null,
        #[ArrayOf(Skill::class)]
        public array $skills = []
    )
    {
    }
}