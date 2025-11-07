<?php

namespace aportela\SimpleFSCache;

class Cache implements \Psr\SimpleCache\CacheInterface
{
    protected \Psr\Log\LoggerInterface $logger;

    private string $basePath;
    private null|int|\DateInterval $defaultTTL = null;

    private \aportela\SimpleFSCache\CacheFormat $format;

    public function __construct(\Psr\Log\LoggerInterface $logger, string $basePath, null|int|\DateInterval $defaultTTL = null, \aportela\SimpleFSCache\CacheFormat $format = \aportela\SimpleFSCache\CacheFormat::NONE)
    {
        $this->logger = $logger;
        $this->setBasePath($basePath);
        $this->format = $format;
        $this->defaultTTL = $defaultTTL;
    }

    public function __destruct() {}

    private function hasDefaultTTL(): bool
    {
        return ($this->defaultTTL !== null);
    }

    public function setDefaultTTL(null|int|\DateInterval $ttl = null): void
    {
        $this->defaultTTL = $ttl;
    }

    public function getDefaultTTL(): null|int|\DateInterval
    {
        return ($this->defaultTTL);
    }

    public function hasTTL(string $key): bool
    {
        return (file_exists($this->getCacheTTLKeyFilePath($key)));
    }

    private function saveTTL(string $key, int $timestamp): bool
    {
        $path = $this->getCacheTTLKeyFilePath($key);
        $totalBytes = file_put_contents($path, $timestamp, LOCK_EX);
        if ($totalBytes > 0) {
            if (touch($path, $timestamp, time())) {
                return (true);
            } else {
                $this->logger->error("\aportela\SimpleFSCache\Cache::saveTTL - Error while touching cache TTL file", [$key, $path]);
                return (false);
            }
        } else {
            $this->logger->error("\aportela\SimpleFSCache\Cache::saveTTL - Error while saving cache TTL file", [$key, $path]);
            return (false);
        }
    }

    private function removeTTL(string $key): bool
    {
        $path = $this->getCacheTTLKeyFilePath($key);
        if (file_exists($path)) {
            if (!unlink($path)) {
                $this->logger->error("\aportela\SimpleFSCache\Cache::set - Error while removing TTL cache file", [$key, $path]);
                return (false);
            }
        } else {
            $this->logger->error("\aportela\SimpleFSCache\Cache::set - Not removing (missing) TTL cache file", [$key, $path]);
        }
        return (true);
    }

    private function getExpireTimeFromTTL(null|int|\DateInterval $ttl): int
    {
        $currentTimestamp = time();
        if ($ttl !== null) {
            if ($ttl instanceof \DateInterval) {
                $dateTime = new \DateTime();
                $dateTime->setTimestamp($currentTimestamp);
                return ($dateTime->add($ttl)->getTimestamp());
            } elseif (is_int($ttl)) {
                return ($currentTimestamp + $ttl);
            } else {
                return ($currentTimestamp);
            }
        } else {
            return ($currentTimestamp);
        }
    }

    public function isExpired(string $key): bool
    {
        $path = $this->getCacheTTLKeyFilePath($key);
        $filemtime = filemtime($path);
        $currentTimestamp = time();
        if (is_int($filemtime)) {
            return ($filemtime <= $currentTimestamp);
        } else {
            $this->logger->error("\aportela\SimpleFSCache\Cache::isExpired - Error while getting cache TTL file modification time", [$key, $path, $filemtime, $currentTimestamp]);
            return (false);
        }
    }

    public function setBasePath(string $basePath): void
    {
        if (! empty($basePath)) {
            if (!file_exists(($basePath))) {
                $this->logger->info("\aportela\SimpleFSCache\Cache::setBasePath - Creating missing path: {$basePath}");
                if (! mkdir($basePath, 0750)) {
                    $this->logger->error("\aportela\SimpleFSCache\Cache::setBasePath - Error while creating missing path: {$basePath}");
                    throw new \aportela\SimpleFSCache\Exception\CacheException("Error while creating missing path: {$basePath}");
                }
            }
            $path = realpath($basePath);
            if (is_string($path) && ! empty($path)) {
                $this->basePath = $path;
            } else {
                $this->logger->error("\aportela\SimpleFSCache\Cache::setBasePath - Error: invalid path {$basePath}");
                throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException("invalid path {$basePath}");
            }
        } else {
            $this->logger->error("\aportela\SimpleFSCache\Cache::setBasePath - empty path");
            throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException("empty path");
        }
    }

    public function getBasePath(): string
    {
        return ($this->basePath);
    }

    public function setFormat(\aportela\SimpleFSCache\CacheFormat $format): void
    {
        $this->format = $format;
    }

    public function getFormat(): \aportela\SimpleFSCache\CacheFormat
    {
        return ($this->format);
    }

    public function getCacheKeyDirectoryPath(string $key): string
    {
        return ($this->basePath . DIRECTORY_SEPARATOR . mb_substr($key, 0, 1) . DIRECTORY_SEPARATOR . mb_substr($key, 1, 1) . DIRECTORY_SEPARATOR . mb_substr($key, 2, 1) . DIRECTORY_SEPARATOR . mb_substr($key, 3, 1));
    }

    public function getCacheKeyFilePath(string $key): string
    {
        $basePath = $this->getCacheKeyDirectoryPath($key);
        if ($this->format !== \aportela\SimpleFSCache\CacheFormat::NONE) {
            return (sprintf("%s%s%s.%s", $basePath, DIRECTORY_SEPARATOR, $key, $this->format->value));
        } else {
            return ($basePath . DIRECTORY_SEPARATOR . $key);
        }
    }

    public function getCacheTTLKeyFilePath(string $key): string
    {
        return ($this->getCacheKeyDirectoryPath($key) . DIRECTORY_SEPARATOR . $key . ".ttl");
    }

    /**
     * Fetches a value from the cache.
     *
     * @param string $key     The unique key of this item in the cache.
     * @param mixed  $default Default value to return if the key does not exist.
     *
     * @return mixed The value of the item from the cache, or $default in case of cache miss.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function get(string $key, mixed $default = null): mixed
    {
        if (empty($key)) {
            throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException("empty cache key");
        }
        try {
            $path = $this->getCacheKeyFilePath($key);
            if (file_exists($path)) {
                if ($this->hasTTL($key) && $this->isExpired($key)) {
                    $this->logger->notice("\aportela\SimpleFSCache\Cache::get - Cache file expired", [$key]);
                    return ($default);
                }
                $cacheData = file_get_contents($path);
                if (is_string($cacheData)) {
                    return ($cacheData);
                } else {
                    $this->logger->error("\aportela\SimpleFSCache\Cache::get - Invalid cache data content type", [$key, gettype($cacheData)]);
                    return ($default);
                }
            } else {
                $this->logger->debug("\aportela\SimpleFSCache\Cache::get - Cache file not found", [$key, $path]);
                return ($default);
            }
        } catch (\Throwable $e) {
            $this->logger->error("\aportela\SimpleFSCache\Cache::get - Error while loading cache file (unhandled exception)", [$key, $e->getMessage(), $e->getCode()]);
            return ($default);
        }
    }

    /**
     * Persists data in the cache, uniquely referenced by a key with an optional expiration TTL time.
     *
     * @param string                 $key   The key of the item to store.
     * @param mixed                  $value The value of the item to store, must be serializable.
     * @param null|int|\DateInterval $ttl   Optional. The TTL value of this item. If no value is sent and
     *                                      the driver supports TTL then the library may set a default value
     *                                      for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function set(string $key, mixed $value, null|int|\DateInterval $ttl = null): bool
    {
        if (empty($key)) {
            throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException("empty cache key");
        }
        try {
            if (! is_string($value)) {
                $this->logger->error("\aportela\SimpleFSCache\Cache::set - Error while saving cache (unsupported value)", [$key, gettype($value)]);
                return (false);
            }
            if (! empty(mb_trim($value))) {
                $directoryPath = $this->getCacheKeyDirectoryPath($key);
                if (! file_exists($directoryPath)) {
                    if (!mkdir($directoryPath, 0750, true)) {
                        $this->logger->error("\aportela\SimpleFSCache\Cache::set - Error while creating cache directory path", [$key, $directoryPath]);
                        return (false);
                    }
                }
                $path = $this->getCacheKeyFilePath($key);
                $totalBytes = file_put_contents($path, $value, LOCK_EX);
                if ($totalBytes > 0) {
                    if ($ttl !== null || $this->hasDefaultTTL()) {
                        if ($this->removeTTL($key)) {
                            return ($this->saveTTL($key, $this->getExpireTimeFromTTL($ttl ?? $this->defaultTTL)));
                        } else {
                            return (false);
                        }
                    } else {
                        return ($this->removeTTL($key));
                    }
                } else {
                    $this->logger->error("\aportela\SimpleFSCache\Cache::set - Error while saving cache file", [$key, $path]);
                    return (false);
                }
            } else {
                $this->logger->info("\aportela\SimpleFSCache\Cache::set - Cache value is empty, saving ignored", [$key]);
                return (false);
            }
        } catch (\Throwable $e) {
            $this->logger->error("\aportela\SimpleFSCache\Cache::set - Error while saving cache (unhandled exception)", [$key, $e->getMessage(), $e->getCode()]);
            return (false);
        }
    }

    /**
     * Delete an item from the cache by its unique key.
     *
     * @param string $key The unique cache key of the item to delete.
     *
     * @return bool True if the item was successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function delete(string $key): bool
    {
        if (empty($key)) {
            throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException("empty cache key");
        }
        try {
            $path = $this->getCacheKeyFilePath($key);
            if (file_exists($path)) {
                if (unlink($path)) {
                    return (true);
                } else {
                    $this->logger->error("\aportela\SimpleFSCache\Cache::delete - Error deleting cache file", [$key, $path]);
                    return (false);
                }
            } else {
                $this->logger->info("\aportela\SimpleFSCache\Cache::delete - Cache file not found, ignoring delete", [$key, $path]);
                return (true);
            }
        } catch (\Throwable $e) {
            $this->logger->error("\aportela\SimpleFSCache\Cache::delete - Error deleting cache (unhandled exception)", [$key, $e->getMessage(), $e->getCode()]);
            return (false);
        }
    }

    private function deleteDirectory(string $path): bool
    {
        $directory = new \DirectoryIterator($path);
        foreach ($directory as $item) {
            if ($item->isFile()) {
                if (!unlink($item->getRealPath())) {
                    return (false);
                }
            } elseif ($item->isDir() && !$item->isDot()) {
                if (! $this->deleteDirectory($item->getRealPath())) {
                    return (false);
                }
            }
        }
        return (rmdir($path));
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear(): bool
    {
        $directory = new \DirectoryIterator($this->basePath);
        foreach ($directory as $item) {
            if ($item->isDir() && !$item->isDot()) {
                if (! $this->deleteDirectory($item->getRealPath())) {
                    return (false);
                }
            }
        }
        return (true);
    }

    /**
     * Obtains multiple cache items by their unique keys.
     *
     * @param iterable<string> $keys    A list of keys that can be obtained in a single operation.
     * @param mixed            $default Default value to return for keys that do not exist.
     *
     * @return iterable<string, mixed> A list of key => value pairs. Cache keys that do not exist or are stale will have $default as value.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $data = [];
        foreach ($keys as $key) {
            if (empty($key)) {
                throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException("empty cache key");
            }
            $data[$key] = $this->get($key, $default);
        }
        return ($data);
    }

    /**
     * Persists a set of key => value pairs in the cache, with an optional TTL.
     *
     * @param iterable               $values A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl    Optional. The TTL value of this item. If no value is sent and
     *                                       the driver supports TTL then the library may set a default value
     *                                       for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple(iterable $values, null|int|\DateInterval $ttl = null): bool
    {
        foreach ($values as $key => $value) {
            if (empty($key)) {
                throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException("empty cache key");
            }
            if (!$this->set($key, $value, $ttl)) {
                return (false);
            }
        }
        return (true);
    }

    /**
     * Deletes multiple cache items in a single operation.
     *
     * @param iterable<string> $keys A list of string-based keys to be deleted.
     *
     * @return bool True if the items were successfully removed. False if there was an error.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $keys is neither an array nor a Traversable,
     *   or if any of the $keys are not a legal value.
     */
    public function deleteMultiple(iterable $keys): bool
    {
        foreach ($keys as $key) {
            if (empty($key)) {
                throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException("empty cache key");
            }
            if (! $this->delete($key)) {
                return (false);
            }
        }
        return (true);
    }

    /**
     * Determines whether an item is present in the cache.
     *
     * NOTE: It is recommended that has() is only to be used for cache warming type purposes
     * and not to be used within your live applications operations for get/set, as this method
     * is subject to a race condition where your has() will return true and immediately after,
     * another script can remove it making the state of your app out of date.
     *
     * @param string $key The cache item key.
     *
     * @return bool
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function has(string $key): bool
    {
        if (empty($key)) {
            throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException("empty cache key");
        }
        return (file_exists($this->getCacheKeyFilePath($key)));
    }
}
