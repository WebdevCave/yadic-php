<?php

namespace Webdevcave\Yadic;

use Psr\Container\ContainerInterface;
use Psr\SimpleCache\CacheInterface;
use Webdevcave\Yadic\Exceptions\NotFoundException;

class ServiceContainer implements ContainerInterface
{
    private const MAP_CACHE_INDEX = 'map';

    private readonly CacheInterface $cache;
    private ?array $map = null;

    public function __construct(
        CacheInterface $cache = null
    )
    {
        //Fallback to an in-memory cache
        if (is_null($cache)) {
            //TODO
        }

        $this->cache = $cache;
    }

    /**
     * @inheritDoc
     */
    public function get(string $id): mixed
    {
        throw new NotFoundException();
    }

    /**
     * @inheritDoc
     */
    public function has(string $id): bool
    {
        return false;
    }

    public function loadInformationFrom(string $path, string $namespace = '\\', bool $clean = false): void
    {
        if (!$clean && $this->cache->has(self::MAP_CACHE_INDEX)) {
            $this->map = $this->cache->get(self::MAP_CACHE_INDEX);
            return;
        }

        //TODO load map
    }
}
