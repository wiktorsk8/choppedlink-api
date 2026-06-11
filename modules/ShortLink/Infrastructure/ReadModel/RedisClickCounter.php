<?php

declare(strict_types=1);

namespace Module\ShortLink\Infrastructure\ReadModel;

use Module\ShortLink\Application\ReadModel\ClickCounter;

/**
 * Adapts the Redis read model to the application ClickCounter port. Keeps the
 * hash key layout owned in one place (ShortLinkReadModel).
 */
final class RedisClickCounter implements ClickCounter
{
    public function __construct(private ShortLinkReadModel $readModel)
    {
    }

    public function increment(string $slug): void
    {
        $this->readModel->incrementClicks($slug);
    }
}
