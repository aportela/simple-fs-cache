# simple-fs-cache

This is a simple library to store and retrieve data from a disk cache (I'm sure there are more serious alternatives but this is my tiny approach to be used in some of my personal projects, should not be taken too seriously).

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

        $cache = new \aportela\SimpleFSCache\Cache($logger, \aportela\SimpleFSCache\CacheFormat::TXT, $cachePath, false);

        // json example data
        $data = json_encode(array("str" => "this is the data to store in cache"));

        // you can use another hash algorithm (sha1?) if you don't trust that MD5 value is unique
        $cacheUniqueIdentifier = md5($data);

        if ($cache->save($cacheUniqueIdentifier, $data)) {
            $cachedData = $cache->get($cacheUniqueIdentifier);
            if ($cachedData !== false) {
                echo "Cache load sucessfully, contents: {$cachedData}" . PHP_EOL;
            }
            $cache->remove($cacheUniqueIdentifier);
        } else {
            echo "Error saving cache" . PHP_EOL;
        }
    } catch (\aportela\SimpleFSCache\Exception\FileSystemException $e) {
        // this exception is thrown when cache path creation failed
        echo "Cache filesystem error: " . $e->getMessage();
    }
```

![PHP Composer](https://github.com/aportela/simple-fs-cache/actions/workflows/php.yml/badge.svg)
