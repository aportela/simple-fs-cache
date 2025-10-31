<?php

namespace aportela\SimpleFSCache;

class Cache
{
    protected \Psr\Log\LoggerInterface $logger;

    private ?string $cachePath = null;
    private bool $enabled = true;
    private \aportela\SimpleFSCache\CacheFormat $format;

    public function __construct(\Psr\Log\LoggerInterface $logger, \aportela\SimpleFSCache\CacheFormat $format = \aportela\SimpleFSCache\CacheFormat::NONE, ?string $cachePath = null)
    {
        $this->logger = $logger;
        $this->logger->debug("SimpleFSCache\Cache::__construct");
        if (! empty($cachePath)) {
            $this->cachePath = ($path = realpath($cachePath)) ? $path : null;
        }
        $this->enabled = ! empty($this->cachePath);
        $this->format = $format;
    }

    public function __destruct()
    {
        $this->logger->debug("SimpleFSCache\Cache::__destruct");
    }

    public function isEnabled()
    {
        return ($this->enabled);
    }

    /**
     * return cache directory path
     */
    private function getCacheDirectoryPath(string $hash): string
    {
        return ($this->cachePath . DIRECTORY_SEPARATOR . mb_substr($hash, 0, 1) . DIRECTORY_SEPARATOR . mb_substr($hash, 1, 1) . DIRECTORY_SEPARATOR . mb_substr($hash, 2, 1) . DIRECTORY_SEPARATOR . mb_substr($hash, 3, 1));
    }

    /**
     * return cache file path
     */
    private function getCacheFilePath(string $hash): string
    {
        $basePath = $this->getCacheDirectoryPath($hash);
        switch ($this->format) {
            case \aportela\SimpleFSCache\CacheFormat::JSON:
                return ($basePath . DIRECTORY_SEPARATOR . $hash . ".json");
            case \aportela\SimpleFSCache\CacheFormat::XML:
                return ($basePath . DIRECTORY_SEPARATOR . $hash . ".xml");
            case \aportela\SimpleFSCache\CacheFormat::TXT:
                return ($basePath . DIRECTORY_SEPARATOR . $hash . ".txt");
            case \aportela\SimpleFSCache\CacheFormat::HTML:
                return ($basePath . DIRECTORY_SEPARATOR . $hash . ".html");
            default:
                return ($basePath . DIRECTORY_SEPARATOR . $hash);
        }
    }

    /**
     * save current raw data into disk cache
     */
    public function save(string $hash, string $raw): bool
    {
        if ($this->enabled) {
            try {
                if (! empty(mb_trim($raw))) {
                    $this->logger->debug("SimpleFSCache\Cache::save", [$hash, $this->cachePath, $this->getCacheFilePath($hash)]);
                    $directoryPath = $this->getCacheDirectoryPath($hash);
                    if (! file_exists($directoryPath)) {
                        if (!mkdir($directoryPath, 0750, true)) {
                            $this->logger->error("Error creating cache directory", [$hash, $directoryPath]);
                            return (false);
                        }
                    }
                    return (file_put_contents($this->getCacheFilePath($hash), $raw) > 0);
                } else {
                    $this->logger->warning("Cache value is empty, saving ignored", [$hash]);
                    return (false);
                }
            } catch (\Throwable $e) {
                $this->logger->error("Error saving cache", [$hash, $e->getMessage()]);
                return (false);
            }
        } else {
            return (false);
        }
    }

    /**
     * remove cache entry
     */
    public function remove(string $hash): bool
    {
        if ($this->enabled) {
            try {
                $cacheFilePath = $this->getCacheFilePath($hash);
                $this->logger->debug("SimpleFSCache\Cache::remove", [$hash, $cacheFilePath]);
                if (file_exists($cacheFilePath)) {
                    return (unlink($cacheFilePath));
                } else {
                    return (false);
                }
            } catch (\Throwable $e) {
                $this->logger->error("Error removing cache", [$hash, $e->getMessage()]);
                return (false);
            }
        } else {
            return (false);
        }
    }

    /**
     * read disk cache
     */
    public function get(string $hash): mixed
    {
        if ($this->enabled) {
            try {
                $cacheFilePath = $this->getCacheFilePath($hash);
                $this->logger->debug("SimpleFSCache\Cache::get", [$hash, $cacheFilePath]);
                if (file_exists($cacheFilePath)) {
                    return (file_get_contents($cacheFilePath));
                } else {
                    $this->logger->debug("Cache not found", [$hash, $this->cachePath, $cacheFilePath]);
                    return (false);
                }
            } catch (\Throwable $e) {
                $this->logger->error("Error loading cache", [$hash, $e->getMessage()]);
                return (false);
            }
        } else {
            return (false);
        }
    }
}
