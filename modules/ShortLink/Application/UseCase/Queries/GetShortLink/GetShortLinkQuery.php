<?php

declare(strict_types=1);

namespace Module\ShortLink\Application\UseCase\Queries\GetShortLink;

interface GetShortLinkQuery
{
    public function execute(string $id): ?ShortLinkDTO;
}
