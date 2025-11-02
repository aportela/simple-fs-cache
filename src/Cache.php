<?php

namespace aportela\SimpleFSCache;

class Cache
{
    protected \Psr\Log\LoggerInterface $logger;

    private ?string $path = null;
    private bool $enabled = true;
    private \aportela\SimpleFSCache\CacheFormat $format;
    private bool $ignoreExistingCache = false;

    public function __construct(\Psr\Log\LoggerInterface $logger, \aportela\SimpleFSCache\CacheFormat $format = \aportela\SimpleFSCache\CacheFormat::NONE, ?string $path = null, bool $ignoreExistingCache = false)
    {
        $this->logger = $logger;
        if (! empty($path)) {
            if (!file_exists(($path))) {
                $this->logger->info("\aportela\SimpleFSCache\Cache::__construct - Creating missing path: {$path}");
                if (! mkdir($path, 0750)) {
                    $this->logger->error("\aportela\SimpleFSCache\Cache::__construct - Error creating missing path: {$path}");
                    throw new \Exception("Error creating missing path: {$path}");
                }
            }
            $this->path = ($path = realpath($path)) ? $path : null;
        }
        $this->enabled = ! empty($this->path);
        $this->format = $format;
        $this->ignoreExistingCache = $ignoreExistingCache;
    }

    public function __destruct()
    {
    }

    public function isEnabled(): bool
    {
        return ($this->enabled);
    }

    public function setFormat(\aportela\SimpleFSCache\CacheFormat $format): void
    {
        $this->format = $format;
    }

    /**
     * return cache directory path
     */
    private function getCacheDirectoryPath(string $identifier): string
    {
        return ($this->path . DIRECTORY_SEPARATOR . mb_substr($identifier, 0, 1) . DIRECTORY_SEPARATOR . mb_substr($identifier, 1, 1) . DIRECTORY_SEPARATOR . mb_substr($identifier, 2, 1) . DIRECTORY_SEPARATOR . mb_substr($identifier, 3, 1));
    }

    /**
     * return cache file path
     */
    private function getCacheFilePath(string $identifier): string
    {
        $basePath = $this->getCacheDirectoryPath($identifier);
        if ($this->format !== \aportela\SimpleFSCache\CacheFormat::NONE) {
            return (sprintf("%s%s%s.%s", $basePath, DIRECTORY_SEPARATOR, $identifier, $this->format->value));
        } else {
            return ($basePath . DIRECTORY_SEPARATOR . $identifier);
        }
    }

    /**
     * save current raw data into disk cache
     */
    public function save(string $identifier, string $raw): bool
    {
        if ($this->enabled) {
            try {
                if (! empty(mb_trim($raw))) {
                    $directoryPath = $this->getCacheDirectoryPath($identifier);
                    if (! file_exists($directoryPath)) {
                        if (!mkdir($directoryPath, 0750, true)) {
                            $this->logger->error("\aportela\SimpleFSCache\Cache::save - Error creating cache file path", [$identifier, $directoryPath]);
                            throw new \Exception("Error creating cache file path: {$directoryPath}");
                        }
                    }
                    return (file_put_contents($this->getCacheFilePath($identifier), $raw) > 0);
                } else {
                    $this->logger->info("\aportela\SimpleFSCache\Cache::save - Cache value is empty, saving ignored", [$identifier]);
                    return (false);
                }
            } catch (\Throwable $e) {
                $this->logger->error("\aportela\SimpleFSCache\Cache::save - Error saving cache", [$identifier, $e->getMessage(), $e->getCode()]);
                return (false);
            }
        } else {
            return (false);
        }
    }

    /**
     * remove cache entry
     */
    public function remove(string $identifier): bool
    {
        if ($this->enabled) {
            try {
                $cacheFilePath = $this->getCacheFilePath($identifier);
                if (file_exists($cacheFilePath)) {
                    if (unlink($cacheFilePath)) {
                        return (true);
                    } else {
                        $this->logger->error("\aportela\SimpleFSCache\Cache::remove - Error removing cache file", [$identifier, $cacheFilePath]);
                        return (false);
                    }
                } else {
                    $this->logger->info("\aportela\SimpleFSCache\Cache::remove - Cache file not found, can not remove", [$identifier, $cacheFilePath]);
                    return (false);
                }
            } catch (\Throwable $e) {
                $this->logger->error("Error removing cache", [$identifier, $e->getMessage()]);
                return (false);
            }
        } else {
            return (false);
        }
    }

    /**
     * read disk cache
     */
    public function get(string $identifier): bool|string
    {
        if ($this->enabled && ! $this->ignoreExistingCache) {
            try {
                $cacheFilePath = $this->getCacheFilePath($identifier);
                if (file_exists($cacheFilePath)) {
                    return (file_get_contents($cacheFilePath));
                } else {
                    $this->logger->debug("\aportela\SimpleFSCache\Cache::get - Cache file not found", [$identifier, $cacheFilePath]);
                    return (false);
                }
            } catch (\Throwable $e) {
                $this->logger->error("\aportela\SimpleFSCache\Cache::get - Error loading cache file", [$identifier, $e->getMessage(), $e->getCode()]);
                return (false);
            }
        } else {
            return (false);
        }
    }
}
