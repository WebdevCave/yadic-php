<?php

namespace Webdevcave\Yadic\Tests;

use Exception;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Webdevcave\Yadic\Annotations\ArrayOf;
use Webdevcave\Yadic\Annotations\Inject;
use Webdevcave\Yadic\Annotations\Provides;
use Webdevcave\Yadic\Annotations\Singleton;
use Webdevcave\Yadic\Exceptions\ContainerException;
use Webdevcave\Yadic\Exceptions\NotFoundException;
use Webdevcave\Yadic\ServiceContainer;
use Webdevcave\Yadic\Tests\ExampleNamespace\Candidate;
use Webdevcave\Yadic\Tests\ExampleNamespace\ClassA;
use Webdevcave\Yadic\Tests\ExampleNamespace\ClassB;
use Webdevcave\Yadic\Tests\ExampleNamespace\ClassC;
use Webdevcave\Yadic\Tests\ExampleNamespace\ClassD;
use Webdevcave\Yadic\Tests\ExampleNamespace\InterfaceA;
use Webdevcave\Yadic\Tests\ExampleNamespace\Skill;

#[CoversClass(ServiceContainer::class)]
#[CoversClass(Provides::class)]
#[CoversClass(Inject::class)]
#[CoversClass(Singleton::class)]
#[CoversClass(ArrayOf::class)]
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

    public function testResourceNotFound()
    {
        $this->expectException(NotFoundException::class);

        $this->container->get('inexistent');
    }

    public function testNoAutowirableResource()
    {
        $this->expectException(ContainerException::class);

        $this->container->get(ClassD::class);
    }

    public function testHydrationForVector()
    {
        $data = [
            ['title' => 'PHP'],
            ['title' => 'Java'],
            ['title' => 'Rust'],
            ['title' => 'React'],
        ];
        $instances = $this->container->hydrate(Skill::class, $data);

        $this->assertContainsOnlyInstancesOf(Skill::class, $instances);

        foreach ($data as $key => $skill) {
            $this->assertEquals($skill['title'], $instances[$key]->title);
        }
    }

    public function testHydrationForMatrix()
    {
        $data = [
            'name' => 'John Doe',
            'age' => 25,
            'skills' => [
                ['title' => 'PHP'],
                ['title' => 'Java'],
                ['title' => 'Rust'],
                ['title' => 'React'],
            ],
        ];
        $instance = $this->container->hydrate(Candidate::class, $data);

        $this->assertInstanceOf(Candidate::class, $instance);
        $this->assertEquals($data['name'], $instance->name);
        $this->assertEquals($data['age'], $instance->age);

        foreach ($data['skills'] as $key => $skill) {
            $this->assertEquals($skill['title'], $instance->skills[$key]->title);
        }
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
