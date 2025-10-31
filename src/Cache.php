<?php

namespace aportela\SimpleFSCache;

class Cache
{
    protected \Psr\Log\LoggerInterface $logger;

    public function __construct(\Psr\Log\LoggerInterface $logger)
    {
        $this->logger = $logger;
        $this->logger->debug("SimpleFSCache\Cache::__construct");
    }

    public function __destruct()
    {
        $this->logger->debug("SimpleFSCache\Cache::__destruct");
    }
}
