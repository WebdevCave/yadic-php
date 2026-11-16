<?php

namespace Webdevcave\Yadic\Tests\ExampleNamespace;

use Countable;

/**
 * Uses a DNF type to exercise hydration of intersection/union combinations.
 */
class DnfHolder
{
    public function __construct(
        public (InterfaceA&Countable)|Address|null $value = null,
    ) {
    }
}
