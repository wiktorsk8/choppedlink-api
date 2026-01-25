<?php

declare(strict_types=1);

namespace Module\User\Application\Queries\GetUser;

use Module\User\Application\Queries\GetUser\DTOs\UserDTO;

interface GetUserQuery
{
    public function execute(string $id): UserDTO;
}
