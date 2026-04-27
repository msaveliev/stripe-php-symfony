<?php

declare(strict_types=1);

namespace App\Service\EventLogger;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

final readonly class RedisEventLogger implements EventLoggerInterface
{
    private const int TTL = 259200; // 72 hours

    public function __construct(
        private CacheItemPoolInterface $cache,
    ) {
    }

    /**
     * Check if a webhook event has already been processed.
     *
     * @param string $uniqueKey The Stripe event unique ID to check
     *
     * @return bool True if the event is a duplicate, false if it's new
     *
     * @throws InvalidArgumentException If Redis cache is unavailable
     */
    public function isLogged(string $uniqueKey): bool
    {
        $item = $this->cache->getItem($uniqueKey);

        return $item->isHit();
    }

    public function log(string $uniqueKey): void
    {
        $item = $this->cache->getItem($uniqueKey);

        $item->set($uniqueKey);
        $item->expiresAfter(self::TTL);
        $this->cache->save($item);
    }
}
