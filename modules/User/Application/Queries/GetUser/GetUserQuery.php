<?php

declare(strict_types=1);

namespace App\User\Application\Queries\GetUser;

use App\User\Application\Queries\GetUser\DTOs\UserDTO;

interface GetUserQuery
{
    public function execute(string $id): UserDTO;
}
