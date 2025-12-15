<?php

declare(strict_types=1);

namespace App\User\Application\Queries\GetUser\DTOs;

use App\Shared\DTO\DTO;

class UserDTO extends DTO
{
    public function __construct(
        public readonly string $id,
        public readonly string $email,
    ) {
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'email' => $this->email,
        ];
    }
}
