<?php

declare(strict_types=1);

namespace Webdevcave\Yadic;

use BackedEnum;
use Closure;
use Exception;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use ReflectionClass;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;
use Throwable;
use Webdevcave\DirectoryCrawler\Crawler;
use Webdevcave\SimpleCache\MemoryCache;
use Webdevcave\Yadic\Annotations\ArrayOf;
use Webdevcave\Yadic\Annotations\Inject;
use Webdevcave\Yadic\Annotations\Provides;
use Webdevcave\Yadic\Annotations\Singleton;
use Webdevcave\Yadic\Exceptions\ContainerException;
use Webdevcave\Yadic\Exceptions\NotFoundException;

class ServiceContainer implements ContainerInterface
{
    private array $aliases;
    private readonly CacheInterface $cache;
    private array $singletons;

    /**
     * @param CacheInterface|null $cache
     *
     * @throws InvalidArgumentException
     */
    public function __construct(?CacheInterface $cache = null)
    {
        //Fallback to an in-memory cache
        if (is_null($cache)) {
            $cache = new MemoryCache();
        }

        $this->cache = $cache;
        $this->aliases = $cache->get('aliases', []);
        $this->singletons = $cache->get('singletons', []);
    }

    /**
     * @throws InvalidArgumentException
     */
    public function __destruct()
    {
        $this->cache->set('aliases', $this->aliases);
        $this->cache->set('singletons', $this->singletons);
    }

    /**
     * @param string $alias
     * @param string $concrete
     *
     * @return void
     */
    public function addAlias(string $alias, string $concrete): void
    {
        $this->aliases[$alias] = $concrete;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @template T
     *
     * @param T     $id        Identifier of the entry to look for.
     * @param array $arguments Predefined arguments.
     *
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     *
     * @return T
     */
    public function get(string $id, array $arguments = []): mixed
    {
        if (isset($this->singletons[$id])) {
            return $this->singletons[$id];
        }

        if (!$this->has($id)) {
            throw new NotFoundException();
        }

        try {
            $className = $this->aliases[$id] ?? $id;
            $reflectionClass = new ReflectionClass($className);

            if ($constructor = $reflectionClass->getConstructor()) {
                $arguments = $this->createArguments($constructor, $arguments);
            }

            $instance = $reflectionClass->newInstanceArgs($arguments);

            if (!empty($reflectionClass->getAttributes(Singleton::class))) {
                $this->singletons[$id] = $instance;
            }

            return $instance;
        } catch (Throwable $previous) {
            throw new ContainerException($previous->getMessage(), $previous->getCode(), $previous);
        }
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has(string $id): bool
    {
        return isset($this->singletons[$id])
            || isset($this->aliases[$id])
            || class_exists($id);
    }

    /**
     * @template T
     *
     * @param T     $className
     * @param array $data
     *
     * @throws ContainerException
     *
     * @return T|T[]
     */
    public function hydrate(string $className, array $data = []): mixed
    {
        try {
            if (array_is_list($data)) {
                return $this->hydrateArray($className, $data);
            }

            return $this->hydrateByClassName($className, $data);
        } catch (Throwable $e) {
            throw new ContainerException('Could not hydrate object', $e->getCode(), $e);
        }
    }

    /**
     * @param callable $function
     * @param array    $arguments
     *
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     *
     * @return mixed
     */
    public function invoke(callable $function, array $arguments = []): mixed
    {
        $reflection = new ReflectionFunction(Closure::fromCallable($function));
        $arguments = $this->createArguments($reflection, $arguments);

        return $reflection->invokeArgs($arguments);
    }

    /**
     * @param string $directory
     * @param string $namespace
     * @param bool   $enforce
     *
     * @throws ReflectionException
     *
     * @return void
     */
    public function loadDefinitionsFromDirectory(string $directory, string $namespace, bool $enforce = false): void
    {
        $crawler = new Crawler($directory);
        $classes = $crawler->classes($namespace, $enforce);

        foreach ($classes as $class) {
            $reflectionClass = new ReflectionClass($class);

            if ($provideAttrs = $reflectionClass->getAttributes(Provides::class)) {
                foreach ($provideAttrs as $attr) {
                    /* @var $provide Provides */
                    $provide = $attr->newInstance();
                    $this->addAlias($provide->index, $class);
                }
            }
        }
    }

    /**
     * @param ReflectionMethod|ReflectionFunction $reflectionMethod
     * @param array                               $arguments
     *
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     * @throws ContainerExceptionInterface
     *
     * @return array
     */
    private function createArguments(
        ReflectionMethod|ReflectionFunction $reflectionMethod,
        array $arguments = []
    ): array {
        foreach ($reflectionMethod->getParameters() as $reflectionParameter) {
            $argumentName = $reflectionParameter->getName();

            if (array_key_exists($argumentName, $arguments)) {
                continue;
            }

            $injectAttrs = $reflectionParameter->getAttributes(Inject::class);
            $typeAlias = !empty($injectAttrs) ? $injectAttrs[0]->newInstance()->index : null;

            if ($typeAlias) {
                $arguments[$argumentName] = $this->get($typeAlias);
                continue;
            }

            if (!$reflectionParameter->hasType()) {
                continue;
            }

            $types = $this->extractTypes($reflectionParameter);
            $candidates = array_values(array_filter($types, fn (array $type) => !$type['isBuiltin']));
            $lastError = null;

            foreach ($candidates as $candidate) {
                // Enums can't be instantiated, so they are never autowired
                if (!$this->has($candidate['name']) || enum_exists($candidate['name'])) {
                    continue;
                }

                try {
                    $arguments[$argumentName] = $this->get($candidate['name']);
                    continue 2;
                } catch (Throwable $e) {
                    $lastError = $e;
                }
            }

            if ($reflectionParameter->isDefaultValueAvailable()) {
                $arguments[$argumentName] = $reflectionParameter->getDefaultValue();
                continue;
            }

            // A parameter typed with a single class keeps propagating the autowiring error,
            // while nullable union types fall back to null.
            $isUnion = count(array_filter($types, fn (array $type) => $type['name'] !== 'null')) > 1;

            if ($reflectionParameter->allowsNull() && (!$lastError || $isUnion)) {
                $arguments[$argumentName] = null;
                continue;
            }

            if ($lastError) {
                throw $lastError;
            }

            throw new Exception("Could not inject parameter: $argumentName");
        }

        return $arguments;
    }

    /**
     * Flattens the declared type of parameter into a list of named types.
     * Intersection types (including DNF groups) cannot be instantiated and are ignored.
     *
     * @param ReflectionParameter $parameter
     *
     * @return array<int, array{name: string, isBuiltin: bool}>
     */
    private function extractTypes(ReflectionParameter $parameter): array
    {
        $reflectionType = $parameter->getType();

        $namedTypes = match (true) {
            $reflectionType instanceof ReflectionNamedType => [$reflectionType],
            $reflectionType instanceof ReflectionUnionType => array_filter(
                $reflectionType->getTypes(),
                fn ($type) => $type instanceof ReflectionNamedType
            ),
            default => [],
        };

        $types = [];

        foreach ($namedTypes as $namedType) {
            $name = $namedType->getName();
            $isBuiltin = $namedType->isBuiltin();

            if (!$isBuiltin) {
                $lower = strtolower($name);

                if ($lower === 'self' || $lower === 'static') {
                    $name = $parameter->getDeclaringClass()?->getName() ?? $name;
                } elseif ($lower === 'parent') {
                    $name = $parameter->getDeclaringClass()?->getParentClass()?->getName() ?? $name;
                }
            }

            $types[] = ['name' => $name, 'isBuiltin' => $isBuiltin];
        }

        return $types;
    }

    /**
     * @template T
     *
     * @param T     $classOrObject
     * @param array $data
     *
     * @return T[]
     */
    private function hydrateArray(string $classOrObject, array $data): mixed
    {
        $objects = [];

        foreach ($data as $item) {
            $objects[] = $this->hydrateItem($classOrObject, $item);
        }

        return $objects;
    }

    /**
     * @param string $className
     * @param array  $data
     *
     * @throws ContainerException
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
     *
     * @return mixed
     */
    private function hydrateByClassName(string $className, array $data): mixed
    {
        $arguments = [];
        $reflection = new ReflectionClass($className);

        if ($reflectionMethod = $reflection->getConstructor()) {
            $argumentsMap = [];

            foreach ($reflectionMethod->getParameters() as $parameter) {
                $types = $this->extractTypes($parameter);
                $typeNames = array_column($types, 'name');
                $arrayType = null;

                if (
                    (in_array('array', $typeNames, true) || in_array('iterable', $typeNames, true))
                    && !empty($arrayOf = $parameter->getAttributes(ArrayOf::class))
                ) {
                    $arrayType = $arrayOf[0]->newInstance()->target;
                }

                $argumentsMap[$parameter->getName()] = [
                    'classes'     => array_column(
                        array_filter($types, fn (array $type) => !$type['isBuiltin']),
                        'name'
                    ),
                    'arrayType'    => $arrayType,
                    'acceptsArray' => !$parameter->hasType()
                        || !empty(array_intersect(['array', 'iterable', 'mixed'], $typeNames)),
                    'acceptsScalar' => !$parameter->hasType()
                        || !empty(array_intersect(
                            ['string', 'int', 'float', 'bool', 'false', 'true', 'mixed'],
                            $typeNames
                        )),
                    'allowsNull' => $parameter->allowsNull(),
                ];
            }

            foreach (array_intersect_key($data, $argumentsMap) as $key => $value) {
                $arguments[$key] = $this->hydrateValue($argumentsMap[$key], $value);
            }
        }

        return $this->get($className, $arguments);
    }

    /**
     * Hydrates a single item of a collection. Items already instantiated are kept as is.
     *
     * @param string $className
     * @param mixed  $item
     *
     * @return mixed
     */
    private function hydrateItem(string $className, mixed $item): mixed
    {
        if ($item instanceof $className) {
            return $item;
        }

        if (is_subclass_of($className, BackedEnum::class) && (is_int($item) || is_string($item))) {
            return $className::from($item);
        }

        return $this->hydrateByClassName($className, $item ?? []);
    }

    /**
     * Converts a raw value into the argument expected by a constructor parameter.
     *
     * @param array $map   Parameter metadata built by hydrateByClassName()
     * @param mixed $value Raw value
     *
     * @throws Throwable
     *
     * @return mixed
     */
    private function hydrateValue(array $map, mixed $value): mixed
    {
        if (is_null($value) && $map['allowsNull']) {
            return null;
        }

        if (!is_null($map['arrayType']) && is_iterable($value)) {
            $items = [];

            foreach ($value as $item) {
                $items[] = $this->hydrateItem($map['arrayType'], $item);
            }

            return $items;
        }

        $classes = $map['classes'];

        if (empty($classes) || is_object($value)) {
            return $value;
        }

        // Scalars: only backed enums can be built from them
        if (!is_null($value) && !is_array($value)) {
            foreach ($classes as $class) {
                if (is_subclass_of($class, BackedEnum::class) && (is_int($value) || is_string($value))) {
                    if ($map['acceptsScalar'] || count($classes) > 1) {
                        if ($enum = $class::tryFrom($value)) {
                            return $enum;
                        }

                        continue;
                    }

                    return $class::from($value);
                }
            }

            return $value;
        }

        // Plain lists are kept as is when the parameter accepts arrays (e.g. array|Collection)
        if (is_array($value) && $map['acceptsArray'] && array_is_list($value)) {
            return $value;
        }

        $data = $value ?? [];
        $candidates = array_values(array_filter(
            $classes,
            fn (string $class) => (class_exists($class) || isset($this->aliases[$class]))
                && !is_subclass_of($class, BackedEnum::class)
        ));

        if (count($candidates) === 1 && !$map['acceptsArray']) {
            return $this->hydrateByClassName($candidates[0], $data);
        }

        // Union types: try the classes whose constructors best match the given keys first
        $scores = [];
        foreach ($candidates as $index => $class) {
            $scores[$index] = $this->matchScore($class, $data);
        }
        uksort($scores, fn ($a, $b) => [$scores[$b], $a] <=> [$scores[$a], $b]);

        $lastError = null;

        foreach (array_keys($scores) as $index) {
            try {
                return $this->hydrateByClassName($candidates[$index], $data);
            } catch (Throwable $e) {
                $lastError = $e;
            }
        }

        if ($map['acceptsArray']) {
            return $value;
        }

        throw $lastError ?? new Exception('No hydratable type found for the given value');
    }

    /**
     * Counts how many keys of $data match the constructor parameters of $className.
     *
     * @param string $className
     * @param array  $data
     *
     * @return int
     */
    private function matchScore(string $className, array $data): int
    {
        try {
            $constructor = (new ReflectionClass($this->aliases[$className] ?? $className))->getConstructor();
        } catch (ReflectionException) {
            return -1;
        }

        if (!$constructor) {
            return 0;
        }

        $names = array_map(fn (ReflectionParameter $p) => $p->getName(), $constructor->getParameters());

        return count(array_intersect(array_keys($data), $names));
    }
}
