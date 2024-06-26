<?php

namespace Webdevcave\Yadic\Tests\ExampleNamespace;

trait CountsInstancesTrait
{
    public static int $instancesCounter = 0;

    public static function increaseCounter(): void
    {
        static::$instancesCounter++;
    }

    public static function resetCounter(): void
    {
        static::$instancesCounter = 0;
    }
}
