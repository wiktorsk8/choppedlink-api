<?php

namespace App\ShortLink\Infrastructure\Doctrine\Repository;

class WhiteListedUserRepository
{
    public function getWhiteListedUser(string $shortLinkId, string $userId): ?object
    {
        return [];
    }
}
