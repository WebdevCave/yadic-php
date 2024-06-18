<?php

namespace Webdevcave\Yadic\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::TARGET_PARAMETER)]
class Inject
{
    public function __construct(
        public readonly ?string $index = null
    ) {}
}
