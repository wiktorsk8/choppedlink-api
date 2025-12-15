<?php

namespace App\ShortLink\Infrastructure\Cache\Repositories;

use App\ShortLink\Infrastructure\Doctrine\Entity\ShortLink;
use App\ShortLink\Infrastructure\Doctrine\Repository\ShortLinkRepository;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class ShortLinkCacheRepository
{
    public function __construct(
        protected CacheInterface $cache,
        protected ShortLinkRepository $repository,
        protected LoggerInterface $logger,
    ) {
    }

    /**
     * @throws InvalidArgumentException
     */
    public function getUrl(string $slug): ?ShortLink
    {
        return $this->cache->get($slug, function (ItemInterface $item) use ($slug): ?ShortLink {
            $item->expiresAfter(3600);
            $shortLink = $this->repository->findBySlug($slug);
            $this->logger->info('Cache hit for slug: ' . $slug);
            return $shortLink;
        });
    }

    /**
     * @throws InvalidArgumentException
     */
    public function invalidate(string $slug): void
    {
        $this->cache->delete($slug);
    }
}
