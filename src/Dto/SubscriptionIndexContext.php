<?php

declare(strict_types=1);

namespace App\Dto;

use App\Entity\User;
use Doctrine\Common\Collections\Collection;

final class SubscriptionIndexContext
{
    public function __construct(
        public readonly User $currentUser,
        public readonly bool $isAdmin,
        /** @var Collection<int, User>|array<int, User> */
        public readonly iterable $familyUsers,
        public readonly bool $viewingAllFamily,
        public readonly User $targetUser,
    ) {
    }
}
