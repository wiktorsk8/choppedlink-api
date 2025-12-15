<?php

declare(strict_types=1);

namespace App\User\Application\Repositories;

use App\User\Infrastructure\Doctrine\Entities\User;

interface UserRepository
{
    public function getByEmail(string $email): ?User;
    public function save(User $user): void;
}
