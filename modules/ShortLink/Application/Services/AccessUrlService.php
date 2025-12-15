<?php

declare(strict_types=1);

namespace App\ShortLink\Application\Service;

use App\ShortLink\Application\Entities\ShortLink;
use App\ShortLink\Application\Exceptions\CannotAccessUrlException;
use App\ShortLink\Application\Repositories\ShortLinkRepository;
use App\ShortLink\Infrastructure\Cache\Repositories\ShortLinkCacheRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Repository\WhiteListedUserRepository;

class AccessUrlService
{
    public function __construct(
        protected ShortLinkCacheRepository  $cacheRepository,
        protected WhiteListedUserRepository $whiteListedUserRepository,
        protected ShortLinkRepository       $shortLinkRepository,
        protected EntityManagerInterface    $entityManager
    ) {
    }

    /**
     * @throws InvalidArgumentException
     * @throws CannotAccessUrlException
     */
    public function accessUrl(string $slug, ?string $userIdentifier): ?string
    {
        $shortLink = $this->cacheRepository->getUrl($slug);
        if (null === $shortLink) {
            return null;
        }

        $this->authorizeAccess($shortLink, $userIdentifier);

        if ($shortLink->hasLimitedAccess()) {
            $this->handleLimitedAccessShortLink(shortLink: $shortLink);
        }

        return $shortLink->getUrl();
    }

    /**
     * @throws CannotAccessUrlException|InvalidArgumentException
     */
    private function handleLimitedAccessShortLink(ShortLink $shortLink): void
    {
        $this->entityManager->wrapInTransaction(/**
         * @throws CannotAccessUrlException
         * @throws InvalidArgumentException
         */ function () use ($shortLink) {
            $freshShortLink = $this->shortLinkRepository->find(
                id: $shortLink->getId(),
                lockMode: LockMode::PESSIMISTIC_WRITE
            );

            if ($freshShortLink->getAccessLimit() <= $freshShortLink->getAccessCounter()) {
                throw new CannotAccessUrlException(message: "URL has reached its access limit");
            }

            $freshShortLink->incrementAccessCounter();
            $this->shortLinkRepository->save(entity: $freshShortLink, flush: true);

            $this->cacheRepository->invalidate($freshShortLink->getSlug());
        });
    }

    /**
     * @throws CannotAccessUrlException
     */
    private function authorizeAccess(ShortLink $shortLink, ?string $userIdentifier): void
    {
        if (
            $shortLink->getIsWhiteListed() &&
            null !== $userIdentifier &&
            !$this->onWhiteList($shortLink, $userIdentifier)
        ) {
            throw new CannotAccessUrlException(message: "URL has a white list");
        }
    }

    private function onWhiteList(ShortLink $shortLink, string $userIdentifier): bool
    {
        return (bool)$this->whiteListedUserRepository->getWhiteListedUser($shortLink->getId(), $userIdentifier);
    }
}
