<?php

namespace App\User\Application\Commands;

use App\Shared\Application\Command\Command;

readonly class RegisterUserCommand implements Command
{
    public function __construct(
        public string $id,
        public string $email,
        public string $password,
        public string $passwordConfirmation,
    ) {
    }
}
