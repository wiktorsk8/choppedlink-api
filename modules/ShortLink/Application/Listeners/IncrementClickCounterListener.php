<?php

declare(strict_types=1);

namespace Module\ShortLink\Application\Listeners;

use Module\ShortLink\Application\Events\ShortLinkAccessed;
use Module\ShortLink\Application\Repositories\ShortLinkRepository;

class IncrementClickCounterListener
{
    public function __construct(
        protected ShortLinkRepository $repository,
    ) {
    }

    public function onShortLinkAccessed(ShortLinkAccessed $event): void
    {
        $shortLink = $this->repository->findBySlug($event->slug);
        $shortLink->incrementClickCounter();
        $this->repository->save($shortLink, true);
    }
}
