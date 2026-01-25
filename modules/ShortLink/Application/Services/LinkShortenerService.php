<?php

declare(strict_types=1);

namespace Module\ShortLink\Application\Services;

use Module\ShortLink\Application\Exceptions\GenerateSlugException;
use Module\ShortLink\Application\Repositories\ShortLinkRepository;
use Symfony\Contracts\Cache\CacheInterface;

class LinkShortenerService
{
    public function __construct(
        protected CacheInterface $cache,
        protected ShortLinkRepository $shortLinkRepository,
    ) {
    }

    /**
     * @throws GenerateSlugException
     */
    public function generateSlug(string $url): string
    {
        try {
            $slug = $this->getHash($url);
        } catch (\Exception $e) {
            throw new GenerateSlugException($e->getMessage(), $e->getCode(), $e);
        }

        $collision = $this->checkDatabaseCollision($slug);

        if ($collision) {
            return $this->generateSlug($url);
        }

        return $slug;
    }

    private function getHash(string $url): string
    {
        $salt = openssl_random_pseudo_bytes(5);
        $hash = md5($salt . $url);
        return substr($hash, 0, 7);
    }

    private function checkDatabaseCollision(string $slug): bool
    {
        $shortLink = $this->shortLinkRepository->findBySlug($slug);

        if (null === $shortLink) {
            return false;
        }

        return true;
    }
}
