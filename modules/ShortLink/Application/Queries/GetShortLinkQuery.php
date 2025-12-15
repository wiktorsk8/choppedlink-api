<?php

declare(strict_types=1);

namespace App\ShortLink\Application\Queries;

use App\ShortLink\Application\Queries\Result\ShortLinkDTO;

interface GetShortLink
{
    public function execute(string $id): ?ShortLinkDTO;
}
