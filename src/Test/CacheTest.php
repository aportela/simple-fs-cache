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

    public function testSetWithoutTtl(): void
    {
        // empty / invalid paths disable cache
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, null, \aportela\SimpleFSCache\CacheFormat::JSON);
        $content = json_encode(["method" => "testSet"]);
        $this->assertIsString($content);
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
    }

    public function testGetWithoutTtl(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, null, \aportela\SimpleFSCache\CacheFormat::TXT);
        $content = "method => testGet";
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
        $cachedContent = $this->cache->get($hash, null);
        $this->assertNotNull($cachedContent);
        $this->assertIsString($cachedContent);
        $this->assertNotEmpty($cachedContent);
        $this->assertEquals($content, $cachedContent);
    }

    public function testGetWithTtlExpired(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, 1, \aportela\SimpleFSCache\CacheFormat::TXT);
        $content = "method => testGetExpired";
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
        sleep(2);
        $default = "default";
        $cachedContent = $this->cache->get($hash, $default);
        $this->assertNotNull($cachedContent);
        $this->assertIsString($cachedContent);
        $this->assertNotEmpty($cachedContent);
        $this->assertEquals($default, $cachedContent);
    }

    public function testGetWithTtlNotExpired(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, 2, \aportela\SimpleFSCache\CacheFormat::TXT);
        $content = "method => testGetNotExpired";
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
        $cachedContent = $this->cache->get($hash, "default");
        $this->assertNotNull($cachedContent);
        $this->assertIsString($cachedContent);
        $this->assertNotEmpty($cachedContent);
        $this->assertEquals($content, $cachedContent);
    }

    public function testDelete(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, null, \aportela\SimpleFSCache\CacheFormat::TXT);
        $content = "method => testDelete";
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
        $this->assertTrue($this->cache->delete($hash));
    }

    public function testGetCacheDirectoryPath(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, null, \aportela\SimpleFSCache\CacheFormat::NONE);
        $content = strval(microtime(true));
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
        $path = $this->cache->getCacheKeyDirectoryPath($hash);
        if (! empty(parent::$cachePath)) {
            $this->assertStringStartsWith(parent::$cachePath, $path);
        }
    }

    public function testGetCacheFilePathWithExtension(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, null, \aportela\SimpleFSCache\CacheFormat::TXT);
        $content = strval(microtime(true));
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
        $path = $this->cache->getCacheKeyFilePath($hash);
        $this->assertNotEmpty($path);
        if (! empty(parent::$cachePath)) {
            $this->assertStringStartsWith(parent::$cachePath, $path);
            $this->assertStringEndsWith($hash . "." . \aportela\SimpleFSCache\CacheFormat::TXT->value, $path);
        }
    }

    public function testGetCacheFilePathWithoutExtension(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, null, \aportela\SimpleFSCache\CacheFormat::NONE);
        $content = strval(microtime(true));
        $hash = md5($content);
        $this->assertTrue($this->cache->set($hash, $content));
        $path = $this->cache->getCacheKeyFilePath($hash);
        $this->assertNotEmpty($path);
        if (! empty(parent::$cachePath)) {
            $this->assertStringStartsWith(parent::$cachePath, $path);
            $this->assertStringEndsWith($hash, $path);
        }
    }

    public function testClear(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, parent::$cachePath, null, \aportela\SimpleFSCache\CacheFormat::TXT);
        $this->assertTrue($this->cache->clear());
    }
}
