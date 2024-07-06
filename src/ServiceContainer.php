<?php

declare(strict_types=1);

namespace Webdevcave\Yadic;

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
    public function __construct(CacheInterface $cache = null)
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
        } catch (Exception $previous) {
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
        } catch (Exception $e) {
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

            if (isset($arguments[$argumentName])) {
                continue;
            }

            if ($reflectionParameter->hasType()) {
                $reflectionType = $reflectionParameter->getType();
                $typeAlias = null;

                $injectAttrs = $reflectionParameter->getAttributes(Inject::class);
                if (!empty($injectAttrs)) {
                    $injectAttrs[0]->newInstance()->index;
                }

                if ($typeAlias || !$reflectionType->isBuiltin()) {
                    $arguments[$argumentName] = $this->get($typeAlias ?? $reflectionType->getName());
                    continue;
                }

                if (
                    !$reflectionParameter->isDefaultValueAvailable()
                    && !$reflectionParameter->allowsNull()
                ) {
                    throw new Exception("Could not inject parameter: $argumentName");
                }

                $arguments[$argumentName] = $reflectionParameter->getDefaultValue();
            }
        }

        return $arguments;
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
            $objects[] = $this->hydrateByClassName($classOrObject, $item);
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

        if ($reflection->hasMethod('__construct')) {
            $reflectionMethod = $reflection->getMethod('__construct');
            $argumentsMap = [];

            foreach ($reflectionMethod->getParameters() as $parameter) {
                $reflectionType = $parameter->getType();
                $type = $reflectionType->getName();
                $arrayType = null;

                if (
                    ($type === 'array' || $type === 'iterable')
                    && !empty($arrayOf = $parameter->getAttributes(ArrayOf::class))
                ) {
                    $arrayType = $arrayOf[0]->newInstance()->target;
                }

                $argumentsMap[$parameter->getName()] = [
                    'type'      => $type,
                    'arrayType' => $arrayType,
                    'isBuiltin' => $reflectionType->isBuiltin(),
                ];
            }

            foreach (array_intersect_key($data, $argumentsMap) as $key => $value) {
                $map = $argumentsMap[$key];

                if (!is_null($map['arrayType']) && is_iterable($value)) {
                    $arguments[$key] = [];

                    foreach ($value as $item) {
                        $arguments[$key][] = $this->hydrateByClassName($map['arrayType'], $item);
                    }

                    continue;
                }

                $arguments[$key] = !$map['isBuiltin'] ?
                    $this->hydrateByClassName($map['type'], $value ?? []) : $value;
            }
        }

        return $this->get($className, $arguments);
    }
}
