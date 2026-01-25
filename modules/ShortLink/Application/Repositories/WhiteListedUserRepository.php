<?php

namespace Module\ShortLink\Application\Repositories;

class WhiteListedUserRepository
{
    public function getWhiteListedUser(string $shortLinkId, string $userId): ?object
    {
        return [];
    }
}
