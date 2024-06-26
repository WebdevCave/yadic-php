<?php

namespace Webdevcave\Yadic\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Webdevcave\Yadic\ServiceContainer;
use Webdevcave\Yadic\Tests\ExampleNamespace\ClassA;
use Webdevcave\Yadic\Tests\ExampleNamespace\ClassB;
use Webdevcave\Yadic\Tests\ExampleNamespace\ClassC;
use Webdevcave\Yadic\Tests\ExampleNamespace\InterfaceA;

class ServiceContainerTest extends TestCase
{
    private ?ServiceContainer $container;

    public function testInstancing(): void
    {
        $this->assertInstanceOf(ClassA::class, $this->container->get(ClassA::class));
    }

    public function testInstancingWithParameters(): void
    {
        $object = $this->container->get(ClassB::class);

        $this->assertInstanceOf(ClassB::class, $object);
        $this->assertInstanceOf(ClassA::class, $object->a);
        $this->assertEquals(2, $object->count);
    }

    public function testProvidesAnnotation(): void
    {
        $this->container->loadDefinitionsFromDirectory(
            __DIR__.'/ExampleNamespace',
            __NAMESPACE__.'\\ExampleNamespace\\'
        );

        $this->assertInstanceOf(ClassA::class, $this->container->get(InterfaceA::class));
    }

    public function testSingletonAnnotation(): void
    {
        $this->container->get(ClassA::class);
        $this->container->get(ClassA::class);
        self::assertEquals(1, ClassA::$instancesCounter);

        $this->container->get(ClassB::class);
        $this->container->get(ClassB::class);
        self::assertEquals(2, ClassB::$instancesCounter);
        self::assertEquals(1, ClassA::$instancesCounter);
    }

    public function testInvokeFunctionWithoutParameters(): void
    {
        $a = new ClassA();

        $this->assertTrue($this->container->invoke([$a, 'funcWithoutParameters']));
    }

    public function testInvokeFunctionWithParametersError(): void
    {
        $this->expectException(Exception::class);
        $a = new ClassA();

        $this->assertTrue($this->container->invoke([$a, 'funcWithParameters']));
    }

    public function testInvokeFunctionWithParameters(): void
    {
        $this->assertTrue($this->container->invoke([new ClassA(), 'funcWithParameters'], ['x' => 1]));
    }

    protected function setUp(): void
    {
        $this->container = new ServiceContainer();
        ClassA::resetCounter();
        ClassB::resetCounter();
        ClassC::resetCounter();
    }

    protected function tearDown(): void
    {
        $this->container = null;
    }
}
