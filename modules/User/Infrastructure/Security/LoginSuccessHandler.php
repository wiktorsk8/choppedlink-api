<?php

declare(strict_types=1);

namespace App\User\Infrastructure\Security;

use Firebase\JWT\JWT;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationSuccessHandlerInterface;

class LoginSuccessHandler implements AuthenticationSuccessHandlerInterface
{

    public function onAuthenticationSuccess(Request $request, TokenInterface $token): ?Response
    {
        $user = $token->getUser();
        $payload = [
            'sub' => $user->getUserIdentifier(),
            'iat' => time(),
            'exp' => time() + $this->getTtl(),
            'roles' => $user->getRoles(),
        ];

        $privateKey = $_ENV['JWT_SECRET_KEY'];
        $privateKey = trim($privateKey);

        $jwt = JWT::encode($payload, $privateKey, 'RS256');

        return new JsonResponse([
            'token' => $jwt
        ]);
    }

    // TODO: Move to yaml configuration
    private function getTtl(): int
    {
        return 3600;
    }
}
