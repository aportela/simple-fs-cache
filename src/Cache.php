<?php

namespace aportela\SimpleFSCache;

class Cache
{
    protected \Psr\Log\LoggerInterface $logger;

    private ?string $cachePath = null;
    private bool $enabled = true;
    private \aportela\SimpleFSCache\CacheFormat $format;
    private bool $ignoreExistingCache = false;

    public function __construct(\Psr\Log\LoggerInterface $logger, \aportela\SimpleFSCache\CacheFormat $format = \aportela\SimpleFSCache\CacheFormat::NONE, ?string $cachePath = null, bool $ignoreExistingCache = false)
    {
        $this->logger = $logger;
        $this->logger->debug("SimpleFSCache\Cache::__construct");
        if (! empty($cachePath)) {
            $this->cachePath = ($path = realpath($cachePath)) ? $path : null;
        }
        $this->enabled = ! empty($this->cachePath);
        $this->format = $format;
        $this->ignoreExistingCache = $ignoreExistingCache;
    }

    public function __destruct()
    {
        $this->logger->debug("SimpleFSCache\Cache::__destruct");
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
        return ($this->cachePath . DIRECTORY_SEPARATOR . mb_substr($identifier, 0, 1) . DIRECTORY_SEPARATOR . mb_substr($identifier, 1, 1) . DIRECTORY_SEPARATOR . mb_substr($identifier, 2, 1) . DIRECTORY_SEPARATOR . mb_substr($identifier, 3, 1));
    }

    /**
     * return cache file path
     */
    private function getCacheFilePath(string $identifier): string
    {
        $basePath = $this->getCacheDirectoryPath($identifier);
        switch ($this->format) {
            case \aportela\SimpleFSCache\CacheFormat::JSON:
                return ($basePath . DIRECTORY_SEPARATOR . $identifier . ".json");
            case \aportela\SimpleFSCache\CacheFormat::XML:
                return ($basePath . DIRECTORY_SEPARATOR . $identifier . ".xml");
            case \aportela\SimpleFSCache\CacheFormat::TXT:
                return ($basePath . DIRECTORY_SEPARATOR . $identifier . ".txt");
            case \aportela\SimpleFSCache\CacheFormat::HTML:
                return ($basePath . DIRECTORY_SEPARATOR . $identifier . ".html");
            default:
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
                    $this->logger->debug("SimpleFSCache\Cache::save", [$identifier, $this->cachePath, $this->getCacheFilePath($identifier)]);
                    $directoryPath = $this->getCacheDirectoryPath($identifier);
                    if (! file_exists($directoryPath)) {
                        if (!mkdir($directoryPath, 0750, true)) {
                            $this->logger->error("Error creating cache directory", [$identifier, $directoryPath]);
                            return (false);
                        }
                    }
                    return (file_put_contents($this->getCacheFilePath($identifier), $raw) > 0);
                } else {
                    $this->logger->warning("Cache value is empty, saving ignored", [$identifier]);
                    return (false);
                }
            } catch (\Throwable $e) {
                $this->logger->error("Error saving cache", [$identifier, $e->getMessage()]);
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
                $this->logger->debug("SimpleFSCache\Cache::remove", [$identifier, $cacheFilePath]);
                if (file_exists($cacheFilePath)) {
                    return (unlink($cacheFilePath));
                } else {
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
    public function get(string $identifier): mixed
    {
        if ($this->enabled && ! $this->ignoreExistingCache) {
            try {
                $cacheFilePath = $this->getCacheFilePath($identifier);
                $this->logger->debug("SimpleFSCache\Cache::get", [$identifier, $cacheFilePath]);
                if (file_exists($cacheFilePath)) {
                    return (file_get_contents($cacheFilePath));
                } else {
                    $this->logger->debug("Cache not found", [$identifier, $this->cachePath, $cacheFilePath]);
                    return (false);
                }
            } catch (\Throwable $e) {
                $this->logger->error("Error loading cache", [$identifier, $e->getMessage()]);
                return (false);
            }
        } else {
            return (false);
        }
    }
}
