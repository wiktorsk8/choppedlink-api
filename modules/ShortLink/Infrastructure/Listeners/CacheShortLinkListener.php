<?php

namespace App\ShortLink\Infrastructure\Listeners;

use App\ShortLink\Application\Events\ShortLinkCreated;
use App\ShortLink\Infrastructure\Cache\Repositories\ShortLinkCacheRepository;
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
