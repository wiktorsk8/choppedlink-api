<?php

namespace Module\ShortLink\Application\Events;

use Symfony\Contracts\EventDispatcher\Event;

class ShortLinkAccessed extends Event
{
    public function __construct(
        public string $slug,
    ) {
    }
}
