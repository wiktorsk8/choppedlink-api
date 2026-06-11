<?php

declare(strict_types=1);

namespace Module\ShortLink\Application\UseCase\Commands\RegisterShortLinkClick;

use Module\Shared\Application\Command\Command;

readonly class RegisterShortLinkClick implements Command
{
    public function __construct(
        public string $slug,
    ) {
    }
}
