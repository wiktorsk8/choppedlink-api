<?php

declare(strict_types=1);

namespace Module\ShortLink\Application\Queries;

use Module\ShortLink\Application\Queries\Result\ShortLinkDTO;

interface GetShortLinkQuery
{
    public function execute(string $id): ?ShortLinkDTO;
}
