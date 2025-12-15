<?php

declare(strict_types=1);

namespace App\ShortLink\Application\Queries;

use App\ShortLink\Application\Events\ShortLinkAccessed;
use App\ShortLink\Application\Exceptions\CannotAccessUrlException;
use App\ShortLink\Application\Exceptions\GetUrlException;
use App\ShortLink\Application\Queries\DTOs\GetUrlDTO;
use App\ShortLink\Application\Service\AccessUrlService;
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
