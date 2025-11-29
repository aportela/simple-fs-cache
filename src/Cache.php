<?php

declare(strict_types=1);

namespace aportela\SimpleFSCache;

class Cache implements \Psr\SimpleCache\CacheInterface
{
    private string $basePath;

    public function __construct(protected \Psr\Log\LoggerInterface $logger, string $basePath, private int|\DateInterval|null $defaultTTL = null, private \aportela\SimpleFSCache\CacheFormat $cacheFormat = \aportela\SimpleFSCache\CacheFormat::NONE)
    {
        $this->setBasePath($basePath);
    }

    private function hasDefaultTTL(): bool
    {
        return ($this->defaultTTL !== null);
    }

    public function setDefaultTTL(int|\DateInterval|null $ttl = null): void
    {
        $this->defaultTTL = $ttl;
    }

    public function getDefaultTTL(): int|\DateInterval|null
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
        if (is_int($totalBytes) && $totalBytes > 0) {
            return (true);
        } else {
            $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::saveTTL - Error while saving cache TTL file', [$key, $path]);
            return (false);
        }
    }

    private function removeTTL(string $key): bool
    {
        $path = $this->getCacheTTLKeyFilePath($key);
        if (file_exists($path)) {
            if (!unlink($path)) {
                $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::set - Error while removing TTL cache file', [$key, $path]);
                return (false);
            }
        } else {
            $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::set - Not removing (missing) TTL cache file', [$key, $path]);
        }

        return (true);
    }

    private function getExpireTimeFromTTL(int|\DateInterval|null $ttl): int
    {
        $currentTimestamp = time();
        if ($ttl !== null) {
            if ($ttl instanceof \DateInterval) {
                $dateTime = new \DateTime();
                $dateTime->setTimestamp($currentTimestamp);
                return ($dateTime->add($ttl)->getTimestamp());
            } else {
                return ($currentTimestamp + $ttl);
            }
        } else {
            return ($currentTimestamp);
        }
    }

    public function isExpired(string $key): bool
    {
        $path = $this->getCacheTTLKeyFilePath($key);
        $ttl = file_get_contents($path);
        if (is_string($ttl)) {
            return (intval($ttl) <= time());
        } else {
            $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::isExpired - Error while getting cache TTL timestamp', [$key, $path, $ttl]);
            return (false);
        }
    }

    public function setBasePath(string $basePath): void
    {
        if ($basePath !== '' && $basePath !== '0') {
            if (!file_exists(($basePath))) {
                $this->logger->info(\aportela\SimpleFSCache\Cache::class . '::setBasePath - Creating missing path: ' . $basePath);
                if (! mkdir($basePath, 0o750)) {
                    $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::setBasePath - Error while creating missing path: ' . $basePath);
                    throw new \aportela\SimpleFSCache\Exception\CacheException('Error while creating missing path: ' . $basePath);
                }
            }

            $path = realpath($basePath);
            if (is_string($path)) {
                $this->basePath = $path;
            } else {
                $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::setBasePath - Error: invalid path ' . $basePath);
                throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException('invalid path ' . $basePath);
            }
        } else {
            $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::setBasePath - empty path');
            throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException("empty path");
        }
    }

    public function getBasePath(): string
    {
        return ($this->basePath);
    }

    public function setFormat(\aportela\SimpleFSCache\CacheFormat $cacheFormat): void
    {
        $this->cacheFormat = $cacheFormat;
    }

    public function getFormat(): \aportela\SimpleFSCache\CacheFormat
    {
        return ($this->cacheFormat);
    }

    public function getCacheKeyDirectoryPath(string $key): string
    {
        return ($this->basePath . DIRECTORY_SEPARATOR . mb_substr($key, 0, 1) . DIRECTORY_SEPARATOR . mb_substr($key, 1, 1) . DIRECTORY_SEPARATOR . mb_substr($key, 2, 1) . DIRECTORY_SEPARATOR . mb_substr($key, 3, 1));
    }

    public function getCacheKeyFilePath(string $key): string
    {
        $basePath = $this->getCacheKeyDirectoryPath($key);
        if ($this->cacheFormat !== \aportela\SimpleFSCache\CacheFormat::NONE) {
            return (sprintf("%s%s%s.%s", $basePath, DIRECTORY_SEPARATOR, $key, $this->cacheFormat->value));
        } else {
            return ($basePath . DIRECTORY_SEPARATOR . $key);
        }
    }

    public function getCacheTTLKeyFilePath(string $key): string
    {
        return ($this->getCacheKeyFilePath($key) . ".ttl");
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
        if ($key === '' || $key === '0') {
            throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException("empty cache key");
        }

        try {
            $path = $this->getCacheKeyFilePath($key);
            if (file_exists($path)) {
                if ($this->hasTTL($key) && $this->isExpired($key)) {
                    $this->logger->notice(\aportela\SimpleFSCache\Cache::class . '::get - Cache file expired', [$key]);
                    return ($default);
                }

                $cacheData = file_get_contents($path);
                if (is_string($cacheData)) {
                    return ($cacheData);
                } else {
                    $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::get - Invalid cache data content type', [$key, gettype($cacheData)]);
                    return ($default);
                }
            } else {
                $this->logger->debug(\aportela\SimpleFSCache\Cache::class . '::get - Cache file not found', [$key, $path]);
                return ($default);
            }
        } catch (\Throwable $throwable) {
            $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::get - Error while loading cache file (unhandled exception)', [$key, $throwable->getMessage(), $throwable->getCode()]);
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
    public function set(string $key, mixed $value, int|\DateInterval|null $ttl = null): bool
    {
        if ($key === '' || $key === '0') {
            throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException("empty cache key");
        }

        try {
            if (! is_string($value)) {
                $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::set - Error while saving cache (unsupported value)', [$key, gettype($value)]);
                return (false);
            }

            if (!in_array(mb_trim($value), ['', '0'], true)) {
                $directoryPath = $this->getCacheKeyDirectoryPath($key);
                if (!file_exists($directoryPath) && !mkdir($directoryPath, 0o750, true)) {
                    $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::set - Error while creating cache directory path', [$key, $directoryPath]);
                    return (false);
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
                    $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::set - Error while saving cache file', [$key, $path]);
                    return (false);
                }
            } else {
                $this->logger->info(\aportela\SimpleFSCache\Cache::class . '::set - Cache value is empty, saving ignored', [$key]);
                return (false);
            }
        } catch (\Throwable $throwable) {
            $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::set - Error while saving cache (unhandled exception)', [$key, $throwable->getMessage(), $throwable->getCode()]);
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
        if ($key === '' || $key === '0') {
            throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException("empty cache key");
        }

        try {
            $path = $this->getCacheKeyFilePath($key);
            if (file_exists($path)) {
                if (unlink($path)) {
                    return (true);
                } else {
                    $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::delete - Error deleting cache file', [$key, $path]);
                    return (false);
                }
            } else {
                $this->logger->info(\aportela\SimpleFSCache\Cache::class . '::delete - Cache file not found, ignoring delete', [$key, $path]);
                return (true);
            }
        } catch (\Throwable $throwable) {
            $this->logger->error(\aportela\SimpleFSCache\Cache::class . '::delete - Error deleting cache (unhandled exception)', [$key, $throwable->getMessage(), $throwable->getCode()]);
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
            if ($item->isDir() && !$item->isDot() && ! $this->deleteDirectory($item->getRealPath())) {
                return (false);
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
     * @param iterable<string, mixed> $values   A list of key => value pairs for a multiple-set operation.
     * @param null|int|\DateInterval $ttl       Optional. The TTL value of this item. If no value is sent and
     *                                          the driver supports TTL then the library may set a default value
     *                                          for it or let the driver take care of that.
     *
     * @return bool True on success and false on failure.
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if $values is neither an array nor a Traversable,
     *   or if any of the $values are not a legal value.
     */
    public function setMultiple(iterable $values, int|\DateInterval|null $ttl = null): bool
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
     *
     * @throws \Psr\SimpleCache\InvalidArgumentException
     *   MUST be thrown if the $key string is not a legal value.
     */
    public function has(string $key): bool
    {
        if ($key === '' || $key === '0') {
            throw new \aportela\SimpleFSCache\Exception\InvalidArgumentException("empty cache key");
        }

        return (file_exists($this->getCacheKeyFilePath($key)));
    }
}
