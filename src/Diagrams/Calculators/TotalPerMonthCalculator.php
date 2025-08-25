<?php

declare(strict_types=1);

namespace App\Diagrams\Calculators;

use App\Entity\Family;
use App\Entity\User;

class TotalPerMonthCalculator
{
    /**
     * Returnează totalurile pe categorii pentru un user într-o lună
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
     * Returnează totalurile pe user pentru un Family într-o lună
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
