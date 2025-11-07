<?php

namespace aportela\SimpleFSCache;

class Cache implements \Psr\SimpleCache\CacheInterface
{
    protected \Psr\Log\LoggerInterface $logger;

    private string $basePath;
    private ?int $secondsTTL = null;
    private ?\DateInterval $dateIntervalTTL = null;

    private \aportela\SimpleFSCache\CacheFormat $format;

    public function __construct(\Psr\Log\LoggerInterface $logger, string $basePath, null|int|\DateInterval $ttl = null, \aportela\SimpleFSCache\CacheFormat $format = \aportela\SimpleFSCache\CacheFormat::NONE)
    {
        $this->logger = $logger;
        $this->setBasePath($basePath);
        $this->format = $format;
        if ($ttl !== null) {
            if ($ttl instanceof \DateInterval) {
                $this->dateIntervalTTL = $ttl;
            } elseif (is_int($ttl)) {
                $this->secondsTTL = $ttl;
            }
        }
    }

    public function __destruct() {}

    private function getExpireTime(int $time): int
    {
        if ($this->dateIntervalTTL !== null) {
            $dateTime = new \DateTime();
            $dateTime->setTimestamp($time);
            return ($dateTime->add($this->dateIntervalTTL)->getTimestamp());
        } elseif ($this->secondsTTL !== null) {
            return ($time + $this->secondsTTL);
        } else {
            return ($time);
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
                $filemtime = filemtime($path);
                if (is_int($filemtime)) {
                    $expireTime = $this->getExpireTime(time());
                    if ($filemtime > $expireTime) {
                        return (file_get_contents($path));
                    } else {
                        $this->logger->debug("\aportela\SimpleFSCache\Cache::get - Cache file expired", [$key, $filemtime, $expireTime]);
                        return ($default);
                    }
                } else {
                    $this->logger->error("\aportela\SimpleFSCache\Cache::get - Error while getting cache file modification time", [$key, $path]);
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
            if (! empty(mb_trim($value))) {
                $directoryPath = $this->getCacheKeyDirectoryPath($key);
                if (! file_exists($directoryPath)) {
                    if (!mkdir($directoryPath, 0750, true)) {
                        $this->logger->error("\aportela\SimpleFSCache\Cache::set - Error while creating cache file path", [$key, $directoryPath]);
                        return (false);
                    }
                }
                $path = $this->getCacheKeyFilePath($key);
                $totalBytes = file_put_contents($path, $value, LOCK_EX);
                if ($totalBytes > 0) {
                    $currentTimestamp = time();
                    // set file modification time to the (future?) expiration date (from now) using TTL (if found)
                    if (touch($path, $this->getExpireTime($currentTimestamp), $currentTimestamp)) {
                        return (true);
                    } else {
                        $this->logger->error("\aportela\SimpleFSCache\Cache::set - Error while touching cache file", [$key, $path]);
                        return (false);
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

    /**
     * Wipes clean the entire cache's keys.
     *
     * @return bool True on success and false on failure.
     */
    public function clear(): bool
    {
        $directory = new \DirectoryIterator($this->basePath);
        foreach ($directory as $file) {
            if ($file->isFile()) {
                if (! unlink($file->getRealPath())) {
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
