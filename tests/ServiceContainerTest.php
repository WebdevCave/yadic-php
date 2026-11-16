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
use Webdevcave\Yadic\Tests\ExampleNamespace\Address;
use Webdevcave\Yadic\Tests\ExampleNamespace\Candidate;
use Webdevcave\Yadic\Tests\ExampleNamespace\ClassA;
use Webdevcave\Yadic\Tests\ExampleNamespace\ClassB;
use Webdevcave\Yadic\Tests\ExampleNamespace\ClassC;
use Webdevcave\Yadic\Tests\ExampleNamespace\ClassD;
use Webdevcave\Yadic\Tests\ExampleNamespace\DnfHolder;
use Webdevcave\Yadic\Tests\ExampleNamespace\GeoPoint;
use Webdevcave\Yadic\Tests\ExampleNamespace\InterfaceA;
use Webdevcave\Yadic\Tests\ExampleNamespace\Profile;
use Webdevcave\Yadic\Tests\ExampleNamespace\Skill;
use Webdevcave\Yadic\Tests\ExampleNamespace\Status;
use Webdevcave\Yadic\Tests\ExampleNamespace\UnionConsumer;

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
            'name'   => 'John Doe',
            'age'    => 25,
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

    public function testHydrationWithUnionTypeBuildsObjectFromArray(): void
    {
        $profile = $this->container->hydrate(Profile::class, [
            'address' => ['street' => 'Main St', 'city' => 'Springfield'],
        ]);

        $this->assertInstanceOf(Address::class, $profile->address);
        $this->assertEquals('Main St', $profile->address->street);
        $this->assertEquals('Springfield', $profile->address->city);
    }

    public function testHydrationWithUnionTypeKeepsScalarsAndNull(): void
    {
        $profile = $this->container->hydrate(Profile::class, ['address' => 'Main St, 42', 'location' => null]);
        $this->assertSame('Main St, 42', $profile->address);
        $this->assertNull($profile->location);

        $profile = $this->container->hydrate(Profile::class, ['code' => 'ABC']);
        $this->assertSame('ABC', $profile->code);

        $profile = $this->container->hydrate(Profile::class, ['code' => 10]);
        $this->assertSame(10, $profile->code);
    }

    public function testHydrationWithUnionTypePicksBestMatchingClass(): void
    {
        $profile = $this->container->hydrate(Profile::class, ['location' => ['lat' => -23.5, 'lng' => -46.6]]);
        $this->assertInstanceOf(GeoPoint::class, $profile->location);
        $this->assertEquals(-23.5, $profile->location->lat);

        $profile = $this->container->hydrate(Profile::class, ['location' => ['street' => 'Main St']]);
        $this->assertInstanceOf(Address::class, $profile->location);
    }

    public function testHydrationWithUnionTypeKeepsInstantiatedObjects(): void
    {
        $address = new Address('Main St');
        $profile = $this->container->hydrate(Profile::class, ['address' => $address, 'location' => $address]);

        $this->assertSame($address, $profile->address);
        $this->assertSame($address, $profile->location);
    }

    public function testHydrationWithArrayUnionType(): void
    {
        $profile = $this->container->hydrate(Profile::class, ['extra' => ['a', 'b']]);
        $this->assertSame(['a', 'b'], $profile->extra);

        $profile = $this->container->hydrate(Profile::class, ['extra' => ['street' => 'Main St']]);
        $this->assertInstanceOf(Address::class, $profile->extra);

        $profile = $this->container->hydrate(Profile::class, ['extra' => ['foo' => 'bar']]);
        $this->assertSame(['foo' => 'bar'], $profile->extra);
    }

    public function testHydrationWithArrayOfInUnionType(): void
    {
        $profile = $this->container->hydrate(Profile::class, [
            'skills' => [['title' => 'PHP'], new Skill('Go')],
        ]);

        $this->assertContainsOnlyInstancesOf(Skill::class, $profile->skills);
        $this->assertEquals('PHP', $profile->skills[0]->title);
        $this->assertEquals('Go', $profile->skills[1]->title);

        $profile = $this->container->hydrate(Profile::class, ['skills' => null]);
        $this->assertNull($profile->skills);
    }

    public function testHydrationWithEnums(): void
    {
        $profile = $this->container->hydrate(Profile::class, [
            'status'       => 'active',
            'strictStatus' => 'inactive',
        ]);
        $this->assertSame(Status::Active, $profile->status);
        $this->assertSame(Status::Inactive, $profile->strictStatus);

        $profile = $this->container->hydrate(Profile::class, ['status' => 'unknown']);
        $this->assertSame('unknown', $profile->status);

        $this->expectException(ContainerException::class);
        $this->container->hydrate(Profile::class, ['strictStatus' => 'unknown']);
    }

    public function testHydrationWithUntypedParameter(): void
    {
        $profile = $this->container->hydrate(Profile::class, ['untyped' => ['any' => 'value']]);

        $this->assertSame(['any' => 'value'], $profile->untyped);
    }

    public function testHydrationWithInvalidUnionValueThrowsContainerException(): void
    {
        $this->expectException(ContainerException::class);

        $this->container->hydrate(Profile::class, ['location' => ['unknown' => true]]);
    }

    public function testHydrationWithDnfType(): void
    {
        $holder = $this->container->hydrate(DnfHolder::class, ['value' => ['street' => 'Main St']]);

        $this->assertInstanceOf(Address::class, $holder->value);
    }

    public function testAutowiringWithUnionTypes(): void
    {
        $this->container->loadDefinitionsFromDirectory(
            __DIR__.'/ExampleNamespace',
            __NAMESPACE__.'\\ExampleNamespace\\'
        );

        $consumer = $this->container->get(UnionConsumer::class);

        $this->assertInstanceOf(ClassA::class, $consumer->dependency);
        $this->assertSame(3, $consumer->number);
        $this->assertNull($consumer->optional);
        $this->assertInstanceOf(ClassA::class, $consumer->aliased);
    }

    public function testAutowiringWithUnionTypesAcceptsExplicitNull(): void
    {
        $consumer = $this->container->get(UnionConsumer::class, ['aliased' => null]);

        $this->assertNull($consumer->aliased);
    }

    public function testInvokeWithUnionTypes(): void
    {
        $result = $this->container->invoke(fn (ClassD|ClassA $dep, int|string $x = 'y') => [$dep, $x]);

        $this->assertInstanceOf(ClassA::class, $result[0]);
        $this->assertSame('y', $result[1]);
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
