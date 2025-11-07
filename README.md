# simple-fs-cache

This is a simple library to store and retrieve data from a disk cache (I'm sure there are more serious alternatives but this is my tiny approach to be used in some of my personal projects, should not be taken too seriously). The implementation complies with the definition in [PSR-16: Common Interface for Caching Libraries](https://www.php-fig.org/psr/psr-16/)

## Requirements

- mininum php version 8.4

## Install (composer) dependencies:

```Shell
composer require aportela/simple-fs-cache
```

## Code example:

```php
<?php

    require "vendor/autoload.php";

    $logger = new \Psr\Log\NullLogger("");

    try {
        $cachePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . "cache";

        $ttl = null; // never expires
        //$ttl = 60; // cache expires after 60 seconds
        //$ttl = new \DateInterval("PT60M"); // cache expires after 60 minutes

        $cache = new \aportela\SimpleFSCache\Cache($logger, $cachePath, $ttl, \aportela\SimpleFSCache\CacheFormat::TXT);

        // json example data
        $data = json_encode(array("str" => "this is the data to store in cache"));

        // you can use another hash algorithm (sha1?) if you don't trust that MD5 value is unique
        $cacheUniqueIdentifier = md5($data);

        if ($cache->set($cacheUniqueIdentifier, $data)) {
            $cachedData = $cache->get($cacheUniqueIdentifier, null);
            if ($cachedData !== null) {
                echo "Cache load sucessfully, contents: {$cachedData}" . PHP_EOL;
                $cache->delete($cacheUniqueIdentifier);
            } else {
                echo "Cache expired" . PHP_EOL;
            }
        } else {
            echo "Error saving cache" . PHP_EOL;
        }
    } catch (\aportela\SimpleFSCache\Exception\FileSystemException $e) {
        // this exception is thrown when cache path creation failed
        echo "Cache filesystem error: " . $e->getMessage();
    }
```

![PHP Composer](https://github.com/aportela/simple-fs-cache/actions/workflows/php.yml/badge.svg)
