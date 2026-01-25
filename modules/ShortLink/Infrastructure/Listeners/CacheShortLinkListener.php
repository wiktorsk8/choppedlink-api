<?php

namespace Module\ShortLink\Infrastructure\Listeners;

use Module\ShortLink\Application\Events\ShortLinkCreated;
use Module\ShortLink\Infrastructure\Cache\Repositories\ShortLinkCacheRepository;
use Psr\Cache\InvalidArgumentException;


// TODO: Make it async
class CacheShortLinkListener
{
    public function __construct(
        protected ShortLinkCacheRepository $repository
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function onShortLinkCreated(ShortLinkCreated $event): void
    {
        $this->repository->getUrl($event->slug);
    }
}
