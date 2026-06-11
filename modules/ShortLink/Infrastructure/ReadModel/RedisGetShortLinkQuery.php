<?php

declare(strict_types=1);

namespace Module\ShortLink\Infrastructure\ReadModel;

use Module\ShortLink\Application\UseCase\Queries\GetShortLink\GetShortLinkQuery;
use Module\ShortLink\Application\UseCase\Queries\GetShortLink\ShortLinkDTO;

final class RedisGetShortLinkQuery implements GetShortLinkQuery
{
    public function __construct(private ShortLinkReadModel $readModel)
    {
    }

    public function execute(string $id): ?ShortLinkDTO
    {
        $data = $this->readModel->findById($id);
        if ($data === null) {
            return null;
        }

        return new ShortLinkDTO(
            id: $data['id'],
            slug: $data['slug'],
            url: $data['url'],
            clicks: (int) ($data['clicks'] ?? 0),
        );
    }
}
