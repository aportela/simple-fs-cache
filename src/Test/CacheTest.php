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

    public function testEnabled(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, \aportela\SimpleFSCache\CacheFormat::NONE, parent::$cachePath);
        $this->assertTrue($this->cache->isEnabled());
    }

    public function testDisabled(): void
    {
        // empty / invalid paths disable cache
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, \aportela\SimpleFSCache\CacheFormat::NONE, null);
        $this->assertFalse($this->cache->isEnabled());
    }


    public function testSave(): void
    {
        // empty / invalid paths disable cache
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, \aportela\SimpleFSCache\CacheFormat::JSON, parent::$cachePath);
        $content = "{}";
        $hash = md5($content);
        $this->assertTrue($this->cache->save($hash, $content));
    }

    public function testGet(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, \aportela\SimpleFSCache\CacheFormat::TXT, parent::$cachePath);
        $content = "foobar";
        $hash = md5($content);
        $this->assertTrue($this->cache->save($hash, $content));
        $cachedContent = $this->cache->get($hash);
        $this->assertTrue($cachedContent !== false);
        $this->assertIsString($cachedContent);
        $this->assertNotEmpty($cachedContent);
        $this->assertEquals($content, $cachedContent);
    }

    public function testRemove(): void
    {
        $this->cache = new \aportela\SimpleFSCache\Cache(parent::$logger, \aportela\SimpleFSCache\CacheFormat::NONE, parent::$cachePath);
        $content = "0123456789";
        $hash = md5($content);
        $this->assertTrue($this->cache->save($hash, $content));
        $this->assertTrue($this->cache->remove($hash));
    }
}
