<?php

declare(strict_types=1);

namespace App\Controller\Service;

use App\Entity\User;
use App\Repository\UserRepository;

class FamilyService
{
    public function __construct(
        private readonly UserRepository $userRepository,
    ) {
    }

    public function verifyLeavePosibility(User $user): bool
    {
        $family = $user->getFamily();
        $admins = $this->userRepository->findBy([
            'family' => $family,
        ]);

        $admins = array_filter($admins, function (User $user) {
            return in_array(
                'ROLE_ADMIN',
                $user->getRoles()
            );
        });

        if (count($admins) === 1 && $admins[0] == $user) {
            return false;
        }

        return true;
    }
}
