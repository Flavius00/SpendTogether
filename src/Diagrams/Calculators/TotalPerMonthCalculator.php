<?php

declare(strict_types=1);

namespace App\Diagrams\Calculators;

use App\Entity\Family;
use App\Entity\User;

class TotalPerMonthCalculator
{
    /**
     * Returns the totals per category for a user in a month
     */
    public function calculateUserCategoryTotals(User $user, string $selectedDate): array
    {
        $expenses = $user->getExpenses();
        $categoryTotals = [];

        $expenses = $expenses->filter(function($expense) use ($selectedDate) {
            $currentMonth = new \DateTime($selectedDate);
            return $expense->getDate()->format('Y-m') === $currentMonth->format('Y-m');
        });

        foreach ($expenses as $expense) {
            $category = $expense->getCategory()->getName();
            $categoryTotals[$category] = ($categoryTotals[$category] ?? 0) + $expense->getAmount();
        }

        return $categoryTotals;
    }

    /**
     * Returns the totals per user for a Family in a month
     */
    public function calculateFamilyUserExpenses(Family $family, string $selectedDate): array
    {
        $users = $family->getUsers();
        $userExpenses = [];

        foreach ($users as $user) {
            $expenses = $user->getExpenses();
            $userTotal = 0;

            $expenses = $expenses->filter(function($expense) use ($selectedDate) {
                $currentMonth = new \DateTime($selectedDate);
                return $expense->getDate()->format('Y-m') === $currentMonth->format('Y-m');
            });

            foreach ($expenses as $expense) {
                $userTotal += $expense->getAmount();
            }

            if ($userTotal > 0) {
                $userExpenses[$user->getName()] = $userTotal;
            }
        }

        return $userExpenses;
    }
}
