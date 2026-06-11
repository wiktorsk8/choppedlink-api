<?php

declare(strict_types=1);

namespace Module\ShortLink\Infrastructure\ReadModel;

use Module\ShortLink\Application\UseCase\Queries\GetUrl\GetUrlDTO;
use Module\ShortLink\Application\UseCase\Queries\GetUrl\GetUrlQuery;

final class RedisGetUrlQuery implements GetUrlQuery
{
    public function __construct(private ShortLinkReadModel $readModel)
    {
    }

    public function execute(string $slug): ?GetUrlDTO
    {
        $data = $this->readModel->findBySlug($slug);
        if ($data === null) {
            return null;
        }

        // accessLimit is stored as '' when the link has no limit (see ShortLinkReadModel::save).
        $isLimited = ($data['accessLimit'] ?? '') !== '';

        return new GetUrlDTO(
            url: $data['url'],
            isLimited: $isLimited,
        );
    }
}
