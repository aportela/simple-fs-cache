<?php

declare(strict_types=1);

namespace aportela\SimpleFSCache\Test;

require_once dirname(dirname(__DIR__)) . DIRECTORY_SEPARATOR . "vendor" . DIRECTORY_SEPARATOR . "autoload.php";

final class CacheTest extends BaseTest
{
    /**
     * Called once just like normal constructor
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
    }

    public function testOne(): void
    {
        $this->assertTrue(true);
    }
}
