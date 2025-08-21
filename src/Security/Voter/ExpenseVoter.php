<?php

declare(strict_types=1);

namespace App\Security\Voter;

use App\Entity\Expense;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ExpenseVoter extends Voter
{
    public const VIEW = 'EXPENSE_VIEW';
    public const EDIT = 'EXPENSE_EDIT';
    public const DELETE = 'EXPENSE_DELETE';
    public const CREATE = 'EXPENSE_CREATE';
    public const LIST = 'EXPENSE_LIST';

    protected function supports(string $attribute, mixed $subject): bool
    {
        if (in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)) {
            return $subject instanceof Expense;
        }

        if (in_array($attribute, [self::CREATE, self::LIST], true)) {
            return $subject instanceof User;
        }

        return false;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        // Super Admin has access to all actions
        /*if (in_array('ROLE_SUPER_ADMIN', $user->getRoles(), true)) {
            return true;
        }*/

        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            if ($subject instanceof User) {
                return $subject->getFamily() === $user->getFamily();
            }

            if ($subject instanceof Expense) {
                return $subject->getUserObject()?->getFamily() === $user->getFamily();
            }
        }

        switch ($attribute) {
            case self::VIEW:
            case self::EDIT:
            case self::DELETE:
                /** @var Expense $expense */
                $expense = $subject;

                return $expense->getUserObject()?->getId() === $user->getId();

            case self::CREATE:
            case self::LIST:
                /** @var User $targetUser */
                $targetUser = $subject;

                return $targetUser->getId() === $user->getId();
        }

        return false;
    }
}
