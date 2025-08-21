<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Subscription;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class SubscriptionVoter extends Voter
{
    public const LIST = 'SUBSCRIPTION_LIST';
    public const VIEW = 'SUBSCRIPTION_VIEW';
    public const CREATE = 'SUBSCRIPTION_CREATE';
    public const EDIT = 'SUBSCRIPTION_EDIT';
    public const DELETE = 'SUBSCRIPTION_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::LIST, self::VIEW, self::CREATE, self::EDIT, self::DELETE], true)
            && (
                $subject instanceof Subscription
                || $subject instanceof User // for LIST/CREATE, the subject is the target user
            );
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);

        switch ($attribute) {
            case self::LIST:
            case self::CREATE:
                // We allow for self or admin in the same family
                if (!$subject instanceof User) {
                    return false;
                }
                if ($subject->getId() === $user->getId()) {
                    return true;
                }

                return $isAdmin && $user->getFamily() && $subject->getFamily() && $subject->getFamily()->getId() === $user->getFamily()->getId();

            case self::VIEW:
            case self::EDIT:
            case self::DELETE:
                if (!$subject instanceof Subscription) {
                    return false;
                }
                $owner = $subject->getUserObject();
                if ($owner && $owner->getId() === $user->getId()) {
                    // owner can view/edit/delete
                    return true;
                }

                // admin from the same family is allowed (including delete)
                return $isAdmin
                    && $user->getFamily()
                    && $owner
                    && $owner->getFamily()
                    && $user->getFamily()->getId() === $owner->getFamily()->getId();
        }

        return false;
    }
}
