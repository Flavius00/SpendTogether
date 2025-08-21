<?php

declare(strict_types=1);

namespace App\Security;

use App\Repository\AccessTokenRepository;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Http\AccessToken\AccessTokenHandlerInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;

final class AccessTokenHandler implements AccessTokenHandlerInterface
{
    public function __construct(
        private readonly AccessTokenRepository $accessTokenRepository,
    ) {
    }

    public function getUserBadgeFrom(string $accessToken): UserBadge
    {
        $token = $this->accessTokenRepository->findOneBy(['token' => $accessToken]);

        if (!$token) {
            throw new AuthenticationException('Invalid API token.');
        }

        if ($token->getExpiresAt() < new \DateTime()) {// method_exists($token, 'isExpired') && $token->isExpired()
            throw new AuthenticationException('API token expired.');
        }

        $user = $token->getUserObject();
        if (!$user) {
            throw new AuthenticationException('Token has no user.');
        }

        // Return the user directly (bypass provider loading)
        return new UserBadge($user->getUserIdentifier(), fn () => $user);
    }
}
