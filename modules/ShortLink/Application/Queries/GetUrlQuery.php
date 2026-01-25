<?php

declare(strict_types=1);

namespace Module\ShortLink\Application\Queries;

use Module\ShortLink\Application\Events\ShortLinkAccessed;
use Module\ShortLink\Application\Exceptions\CannotAccessUrlException;
use Module\ShortLink\Application\Exceptions\GetUrlException;
use Module\ShortLink\Application\Queries\DTOs\GetUrlDTO;
use Module\ShortLink\Application\Services\AccessUrlService;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;


class GetUrlQuery
{
    public function __construct(
        protected AccessUrlService         $accessUrlService,
        protected EventDispatcherInterface $eventDispatcher
    )
    {
    }

    /**
     * @throws GetUrlException
     */
    public function execute(GetUrlDTO $DTO): ?string
    {
        try {
            $url = $this->accessUrlService->accessUrl(slug: $DTO->slug, userIdentifier: $DTO->userIdentifier);
        } catch (InvalidArgumentException|CannotAccessUrlException $e) {
            throw new GetUrlException($e->getMessage(), $e->getCode(), $e);
        }

        if (null === $url) {
            return null;
        }

        $this->eventDispatcher->dispatch(
            new ShortLinkAccessed($DTO->slug)
        );
        return $url;
    }
}
