<?php

namespace aportela\SimpleFSCache;

class Cache implements \Psr\SimpleCache\CacheInterface
{
    protected \Psr\Log\LoggerInterface $logger;

    private string $basePath;
    private \aportela\SimpleFSCache\CacheFormat $format;

    public function __construct(\Psr\Log\LoggerInterface $logger, string $basePath, \aportela\SimpleFSCache\CacheFormat $format = \aportela\SimpleFSCache\CacheFormat::NONE)
    {
        $this->logger = $logger;
        $this->setBasePath($basePath);
        $this->format = $format;
    }

    public function __destruct() {}

    public function setBasePath(string $basePath): void
    {
        if (! empty($basePath)) {
            if (!file_exists(($basePath))) {
                $this->logger->info("\aportela\SimpleFSCache\Cache::setBasePath - Creating missing path: {$basePath}");
                if (! mkdir($basePath, 0750)) {
                    $this->logger->error("\aportela\SimpleFSCache\Cache::__construct - Error while creating missing path: {$basePath}");
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

    /**
     * return cache directory path
     */
    public function getCacheDirectoryPath(string $key): string
    {
        return ($this->basePath . DIRECTORY_SEPARATOR . mb_substr($key, 0, 1) . DIRECTORY_SEPARATOR . mb_substr($key, 1, 1) . DIRECTORY_SEPARATOR . mb_substr($key, 2, 1) . DIRECTORY_SEPARATOR . mb_substr($key, 3, 1));
    }

    /**
     * return cache file path
     */
    public function getCacheFilePath(string $key): string
    {
        $basePath = $this->getCacheDirectoryPath($key);
        if ($this->format !== \aportela\SimpleFSCache\CacheFormat::NONE) {
            return (sprintf("%s%s%s.%s", $basePath, DIRECTORY_SEPARATOR, $key, $this->format->value));
        } else {
            return ($basePath . DIRECTORY_SEPARATOR . $key);
        }
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
            throw new \Psr\SimpleCache\InvalidArgumentException("invalid cache key");
        }

        try {
            $cacheFilePath = $this->getCacheFilePath($key);
            if (file_exists($cacheFilePath)) {
                $time = filemtime($cacheFilePath);
                if (is_int($time)) { // unix timestamp
                    return (file_get_contents($cacheFilePath));
                } else {
                    // TODO
                    return ($default);
                }
            } else {
                $this->logger->debug("\aportela\SimpleFSCache\Cache::get - Cache file not found", [$key, $cacheFilePath]);
                return ($default);
            }
        } catch (\Throwable $e) {
            $this->logger->error("\aportela\SimpleFSCache\Cache::get - Error loading cache file", [$key, $e->getMessage(), $e->getCode()]);
            // TODO
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
        try {
            if (! empty(mb_trim($value))) {
                $directoryPath = $this->getCacheDirectoryPath($key);
                if (! file_exists($directoryPath)) {
                    if (!mkdir($directoryPath, 0750, true)) {
                        $this->logger->error("\aportela\SimpleFSCache\Cache::save - Error creating cache file path", [$key, $directoryPath]);
                        throw new \aportela\SimpleFSCache\Exception\FileSystemException("Error creating cache file path: {$directoryPath}");
                    }
                }
                return (file_put_contents($this->getCacheFilePath($key), $value, LOCK_EX) > 0);
            } else {
                $this->logger->info("\aportela\SimpleFSCache\Cache::save - Cache value is empty, saving ignored", [$key]);
                return (false);
            }
        } catch (\Throwable $e) {
            $this->logger->error("\aportela\SimpleFSCache\Cache::save - Error saving cache", [$key, $e->getMessage(), $e->getCode()]);
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
        try {
            $cacheFilePath = $this->getCacheFilePath($key);
            if (file_exists($cacheFilePath)) {
                if (unlink($cacheFilePath)) {
                    return (true);
                } else {
                    $this->logger->error("\aportela\SimpleFSCache\Cache::remove - Error removing cache file", [$key, $cacheFilePath]);
                    return (false);
                }
            } else {
                $this->logger->info("\aportela\SimpleFSCache\Cache::remove - Cache file not found, can not remove", [$key, $cacheFilePath]);
                return (false);
            }
        } catch (\Throwable $e) {
            $this->logger->error("Error removing cache", [$key, $e->getMessage()]);
            return (false);
        }
    }

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear(): bool
    {
        return (false);
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
        return ([]);
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
        return (false);
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
            $this->delete($key);
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
        return (file_exists($this->getCacheFilePath($key)));
    }
}
