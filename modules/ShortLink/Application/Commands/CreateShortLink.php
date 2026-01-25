<?php

declare(strict_types=1);

namespace Module\ShortLink\Application\Commands;

use Module\Shared\Application\Command\Command;

readonly class CreateShortLink implements Command
{
    public function __construct(
        public string $id,
        public string $url,
        public bool $isWhiteListed,
        public ?int $accessLimit,
    ) {
    }
}
