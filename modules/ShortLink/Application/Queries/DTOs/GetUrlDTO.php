<?php

namespace Module\ShortLink\Application\Queries\DTOs;

use Module\Shared\DTO\DTO;

class GetUrlDTO extends DTO
{
    public function __construct(
        public readonly string $slug,
        public readonly ?string $userIdentifier
    ) {
    }
}
