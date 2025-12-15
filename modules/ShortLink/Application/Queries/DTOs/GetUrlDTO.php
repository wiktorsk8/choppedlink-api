<?php

namespace App\ShortLink\Application\Queries\DTOs;

use App\Shared\DTO\DTO;

class GetUrlDTO extends DTO
{
    public function __construct(
        public readonly string $slug,
        public readonly ?string $userIdentifier
    ) {
    }
}
