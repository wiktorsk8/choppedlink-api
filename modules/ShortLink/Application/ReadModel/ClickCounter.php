<?php

declare(strict_types=1);

namespace Module\ShortLink\Application\ReadModel;

/**
 * Port for bumping the fast click counter in the read model. The application
 * owns this interface; infrastructure provides the Redis-backed adapter. Keeps
 * the command handler free of any concrete read-model / Redis dependency.
 */
interface ClickCounter
{
    public function increment(string $slug): void;
}
