<?php

declare(strict_types=1);

namespace App\Controller\Service;

use App\Dto\SubscriptionIndexContext;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Voter\SubscriptionVoter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class SubscriptionIndexContextResolver
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $auth,
    ) {
    }

    public function resolveAndAuthorize(Request $request, UserRepository $users, User $currentUser): SubscriptionIndexContext
    {
        $isAdmin = $this->auth->isGranted('ROLE_ADMIN');

        $familyUsers = $currentUser->getFamily() ? $currentUser->getFamily()->getUsers() : [];

        $userParam = $request->query->get('user');
        $viewingAllFamily = $isAdmin && $userParam === '__all__';

        $targetUser = $currentUser;
        if (!$viewingAllFamily && $userParam) {
            $candidate = $users->find((int) $userParam);
            if ($candidate instanceof User) {
                $targetUser = $candidate;
            }
        }

        if ($viewingAllFamily) {
            if (!$currentUser->getFamily()) {
                throw new AccessDeniedException();
            }
        } else {
            if (!$this->auth->isGranted(SubscriptionVoter::LIST, $targetUser)) {
                throw new AccessDeniedException();
            }
        }

        return new SubscriptionIndexContext(
            currentUser: $currentUser,
            isAdmin: $isAdmin,
            familyUsers: $familyUsers,
            viewingAllFamily: $viewingAllFamily,
            targetUser: $targetUser
        );
    }
}
