<?php

declare(strict_types=1);

namespace App\Service;

use App\Dto\ExpenseIndexContext;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Voter\ExpenseVoter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

final class ExpenseIndexContextResolver
{
    public function __construct(
        private readonly AuthorizationCheckerInterface $auth,
    ) {
    }

    public function resolveAndAuthorize(Request $request, UserRepository $users, User $currentUser): ExpenseIndexContext
    {
        $isAdmin = $this->auth->isGranted('ROLE_ADMIN');

        $familyUsers = [];
        if ($isAdmin && $currentUser->getFamily()) {
            $familyUsers = $currentUser->getFamily()->getUsers();
        }

        $userParam = $request->query->get('user');
        $viewingAllFamily = $isAdmin && $userParam === '__all__';

        $targetUser = $currentUser;
        if ($isAdmin && !$viewingAllFamily && $userParam) {
            $candidate = $users->find((int) $userParam);
            if ($candidate instanceof User) {
                $targetUser = $candidate;
            }
        }

        if ($viewingAllFamily) {
            if (!$isAdmin || !$currentUser->getFamily()) {
                throw new AccessDeniedException();
            }
        } else {
            if (!$this->auth->isGranted(ExpenseVoter::LIST, $targetUser)) {
                throw new AccessDeniedException();
            }
        }

        return new ExpenseIndexContext(
            currentUser: $currentUser,
            isAdmin: $isAdmin,
            familyUsers: $familyUsers,
            viewingAllFamily: $viewingAllFamily,
            targetUser: $targetUser
        );
    }
}
