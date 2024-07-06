<?php

namespace Webdevcave\Yadic\Annotations;

use Attribute;

#[Attribute(Attribute::TARGET_PARAMETER | Attribute::TARGET_PROPERTY)]
class ArrayOf
{
    public function __construct(
        public string $target,
    ) {
    }
}
