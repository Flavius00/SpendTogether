<?php

declare(strict_types=1);

namespace App\Diagrams\Calculators;

use App\Entity\Family;
use App\Entity\User;

class SelectedMonthVsLastMonthCalculator
{
    public function calculateUserDailyExpenses(User $user, string $month): array
    {
        $expenses = $user->getExpenses();
        $daysInMonth = $this->getDaysInMonth($month);
        $isCurrentMonth = $this->isCurrentMonth($month);
        $today = (int)(new \DateTime())->format('d');

        $maxDay = $isCurrentMonth ? $today : $daysInMonth;

        $dailyTotals = array_fill(1, $maxDay - 1 + 1, 0);

        foreach ($expenses as $expense) {
            if ($expense->getDate()->format('Y-m') === $month) {
                $day = (int)$expense->getDate()->format('d');
                if ($day <= $maxDay) {
                    $dailyTotals[$day] += $expense->getAmount();
                }
            }
        }

        return $this->convertToCumulative($dailyTotals);
    }

    public function calculateFamilyDailyExpenses(Family $family, string $month): array
    {
        $daysInMonth = $this->getDaysInMonth($month);
        $isCurrentMonth = $this->isCurrentMonth($month);
        $today = (int)(new \DateTime())->format('d');

        $maxDay = $isCurrentMonth ? $today : $daysInMonth;

        $dailyTotals = array_fill(1, $maxDay - 1 + 1, 0);

        foreach ($family->getUsers() as $user) {
            foreach ($user->getExpenses() as $expense) {
                if ($expense->getDate()->format('Y-m') === $month) {
                    $day = (int)$expense->getDate()->format('d');
                    if ($day <= $maxDay) {
                        $dailyTotals[$day] += $expense->getAmount();
                    }
                }
            }
        }

        return $this->convertToCumulative($dailyTotals);
    }

    private function convertToCumulative(array $dailyTotals): array
    {
        $cumulative = [];
        $runningTotal = 0;

        foreach ($dailyTotals as $day => $amount) {
            $runningTotal += $amount;
            $cumulative[$day] = $runningTotal;
        }

        return $cumulative;
    }

    private function getDaysInMonth(string $month): int
    {
        $date = new \DateTime($month . '-01');
        return (int)$date->format('t');
    }

    private function isCurrentMonth(string $month): bool
    {
        $currentMonth = (new \DateTime())->format('Y-m');
        return $month === $currentMonth;
    }

    public function getPreviousMonth(string $month): string
    {
        $date = new \DateTime($month . '-01');
        $date->modify('-1 month');
        return $date->format('Y-m');
    }
}
