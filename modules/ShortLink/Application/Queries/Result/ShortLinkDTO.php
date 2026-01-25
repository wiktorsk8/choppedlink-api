<?php

declare(strict_types=1);

namespace Module\ShortLink\Application\Queries\Result;

use Module\Shared\DTO\DTO;

final class ShortLinkDTO extends DTO
{
    public function __construct(
        public string $id,
        public string $slug,
        public string $url,
    ) {
    }


    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'url' => $this->url,
        ];
    }
}
