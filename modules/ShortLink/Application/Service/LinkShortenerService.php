<?php

declare(strict_types=1);

namespace App\ShortLink\Application\Service;

use App\ShortLink\Application\Exceptions\GenerateSlugException;
use App\ShortLink\Infrastructure\Doctrine\Repository\ShortLinkRepository;
use Symfony\Component\Cache\Adapter\RedisAdapter;
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
