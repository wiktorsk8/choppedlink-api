<?php

namespace App\ShortLink\Application\Events;

use Symfony\Contracts\EventDispatcher\Event;

class ShortLinkCreated extends Event
{
    public function __construct(
        public string $id,
        public string $slug,
    ) {
    }
}
