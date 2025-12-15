<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use Exception;
use Firebase\JWT\ExpiredException;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use SensitiveParameter;
use Symfony\Component\HttpKernel\Log\DebugLoggerInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        protected UserProviderInterface $userProvider
    ) {
    }

    public function getUserBadgeFrom(#[SensitiveParameter] string $accessToken): UserBadge
    {
        try {
            $publicKey = $_ENV['JWT_PUBLIC_KEY'];
            $decodedToken = JWT::decode($accessToken, new Key(trim($publicKey), 'RS256'));
        } catch (ExpiredException $e) {
            throw new AuthenticationException($e->getMessage(), $e->getCode(), $e);
        }  catch (Exception) {
            throw new AuthenticationException("Invalid JWT token");
        }

        $userIdentifier = $decodedToken->sub ?? null;
        if (null === $userIdentifier) {
            throw new AuthenticationException("JWT missing sub claim");
        }

        return new UserBadge($userIdentifier);
    }
}
