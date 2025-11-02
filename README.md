# simple-fs-cache

Custom php filesystem cache

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

    $cachePath = dirname(__FILE__) . DIRECTORY_SEPARATOR . "cache"

    $cache = new \aportela\SimpleFSCache\Cache(parent::$logger, \aportela\SimpleFSCache\CacheFormat::TXT, $cachePath, false);

    $data = "this is the data to store in cache";
    // you can use another hash algorithm if you don't trust that MD5 value is unique
    $cacheUniqueIdentifier = md5($data);

    if ($cache->save($cacheUniqueIdentifier, $data)) {
        $cachedData = $cache->get();
        if ($cachedData !== false) {
            echo "Cache load sucessfully, contents: {$cachedData}" . PHP_EOL;
        }
        $cache->remove($cacheUniqueIdentifier);
    } else {
        echo "Error saving cache" . PHP_EOL;
    }
```

![PHP Composer](https://github.com/aportela/simple-fs-cache/actions/workflows/php.yml/badge.svg)
