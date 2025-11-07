<?php

declare(strict_types=1);

namespace aportela\SimpleFSCache\Test;

require_once dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";


final class CacheTest extends \aportela\SimpleFSCache\Test\BaseTest
{
    protected \aportela\SimpleFSCache\Cache $cache;

    /**
     * Called once just like normal constructor
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    public function testSet(): void
    {
        // empty / invalid paths disable cache
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, null, \aportela\SimpleFSCache\CacheFormat::JSON);
        $content = "{}";
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
    }

    public function testGet(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, null, \aportela\SimpleFSCache\CacheFormat::TXT);
        $content = "foobar1";
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
        $cachedContent = $this->cache->get($hash, null);
        $this->assertNotNull($cachedContent);
        $this->assertIsString($cachedContent);
        $this->assertNotEmpty($cachedContent);
        $this->assertEquals($content, $cachedContent);
    }

    public function testGetExpired(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, 2, \aportela\SimpleFSCache\CacheFormat::TXT);
        $content = "foobar1";
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
        sleep(5);
        $default = "default";
        $cachedContent = $this->cache->get($hash, $default);
        $this->assertNotNull($cachedContent);
        $this->assertIsString($cachedContent);
        $this->assertNotEmpty($cachedContent);
        $this->assertEquals($default, $cachedContent);
    }

    public function testGetNotExpired(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, 5, \aportela\SimpleFSCache\CacheFormat::TXT);
        $content = "foobar1";
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
        sleep(1);
        $cachedContent = $this->cache->get($hash, "default");
        $this->assertNotNull($cachedContent);
        $this->assertIsString($cachedContent);
        $this->assertNotEmpty($cachedContent);
        $this->assertEquals($content, $cachedContent);
    }

    public function testDelete(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, null, \aportela\SimpleFSCache\CacheFormat::NONE);
        $content = "0123456789";
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
        $this->assertTrue($this->cache->delete($hash));
    }

    public function testGetCacheDirectoryPath(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, null, \aportela\SimpleFSCache\CacheFormat::NONE);
        $content = "0123456789";
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
        $path = $this->cache->getCacheKeyDirectoryPath($hash);
        $this->assertTrue($this->cache->delete($hash));
        if (! empty(parent::$cachePath)) {
            $this->assertStringStartsWith(parent::$cachePath, $path);
        }
    }

    public function testGetCacheFilePath(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, null, \aportela\SimpleFSCache\CacheFormat::TXT);
        $content = "0123456789";
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
        $path = $this->cache->getCacheKeyDirectoryPath($hash);
        $this->assertNotEmpty($path);
        $this->assertTrue($this->cache->delete($hash));
        if (! empty(parent::$cachePath)) {
            $this->assertStringStartsWith(parent::$cachePath, $path);
            $this->assertStringEndsWith($hash . "." . \aportela\SimpleFSCache\CacheFormat::TXT->value, $path);
        }
    }
}
