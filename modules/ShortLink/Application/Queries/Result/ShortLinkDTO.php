<?php

declare(strict_types=1);

namespace App\ShortLink\Application\Queries\Result;

use App\Shared\DTO\DTO;

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
