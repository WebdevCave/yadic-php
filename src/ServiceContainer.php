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
     * @throws InvalidArgumentException
     */
    public function __destruct()
    {
        $this->cache->set('aliases', $this->aliases);
        $this->cache->set('singletons', $this->singletons);
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
     * @param string $alias
     * @param string $concrete
     *
     * @return void
     */
    public function addAlias(string $alias, string $concrete): void
    {
        $this->aliases[$alias] = $concrete;
    }
}
