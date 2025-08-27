<?php

namespace App\Diagrams\Calculators;

use App\Dto\ProjectedSpendingResult;
use App\Entity\Family;
use App\Entity\User;

class ProjectedNextMonthSpendingCalculator
{
    public function calculate(User $user): ProjectedSpendingResult
    {
        $currentToDate = 0.0;
        $prevToDate = 0.0;
        $cumCurrent = [];
        $compareIndex = 0;
        $daysInMonth = 30; // generic fallback
        $budgetHit = null;

        $projectedTotal = 0.0;
        $growthRate = 0.0;

        if ($this->existsThreeMonthsPast($user)) {
            $threeMonthsAverage = $this->getPastThreeMonthsAverage($user);

            if ($this->existsLastYearSameMonths($user)) {
                $lastYearThreeMonthsAverage = $this->getLastYearSameMonthsAverage($user);
                $lastYearThisMonth = $this->getLastYearThisMonth($user);

                $growthRate = $threeMonthsAverage > 0.0
                    ? ($lastYearThreeMonthsAverage / $threeMonthsAverage)
                    : 1.0;

                $projectedTotal = $lastYearThisMonth + ($threeMonthsAverage - $lastYearThreeMonthsAverage);
            } else {
                $projectedTotal = $threeMonthsAverage;
            }
        }

        return new ProjectedSpendingResult(
            $currentToDate,   // in this case 0.0, because it's not on current days
            round($projectedTotal, 2),
            $growthRate,
            $budgetHit,
            $prevToDate,
            $cumCurrent,
            $compareIndex,
            $daysInMonth,
            false
        );
    }

    public function calculateFamilyTotal(Family $family): ProjectedSpendingResult
    {
        $totalProjected = 0.0;
        $totalCurrentToDate = 0.0;
        $totalPrevToDate = 0.0;
        $avgGrowthRate = 0.0;
        $userCount = 0;

        foreach ($family->getUsers() as $user) {
            $result = $this->calculate($user);
            $totalProjected += $result->projectedTotal;
            $totalCurrentToDate += $result->currentToDate;
            $totalPrevToDate += $result->prevToDate;
            $avgGrowthRate += $result->growthRate;
            $userCount++;
        }

        $avgGrowthRate = $userCount > 0 ? ($avgGrowthRate / $userCount) : 0.0;

        return new ProjectedSpendingResult(
            round($totalCurrentToDate, 2),
            round($totalProjected, 2),
            round($avgGrowthRate, 2),
            null, // budgetHit - not applicable for family total
            round($totalPrevToDate, 2),
            [], // cumCurrent - empty for family total
            0, // compareIndex - not applicable
            30, // daysInMonth - generic fallback
            false // isProjection
        );
    }

    private function existsThreeMonthsPast(User $user): bool
    {
        return $this->hasExpensesForMonths($user, [1, 2, 3]);
    }

    private function getPastThreeMonthsAverage(User $user): float
    {
        $total = $this->sumExpensesForMonths($user, [1, 2, 3]);
        return round($total / 3, 2);
    }

    private function existsLastYearSameMonths(User $user): bool
    {
        return $this->hasExpensesForMonths($user, [1, 2, 3], true);
    }

    private function getLastYearSameMonthsAverage(User $user): float
    {
        $total = $this->sumExpensesForMonths($user, [1, 2, 3], true);
        return round($total / 3, 2);
    }

    private function getLastYearThisMonth(User $user): float
    {
        $month = new \DateTime('-1 year');
        $expenses = $user->getExpenses()->filter(fn($e) => $e->getDate()->format('Y-m') === $month->format('Y-m'));

        $total = 0.0;
        foreach ($expenses as $e) {
            $total += $e->getAmount();
        }

        return round($total, 2);
    }

    private function hasExpensesForMonths(User $user, array $monthsAgo, bool $lastYear = false): bool
    {
        foreach ($monthsAgo as $m) {
            $date = new \DateTime();
            if ($lastYear) {
                $date->modify("-1 year");
            }
            $date->modify("-$m months");

            $expenses = $user->getExpenses()->filter(fn($e) => $e->getDate()->format('Y-m') === $date->format('Y-m'));
            if ($expenses->isEmpty()) {
                return false;
            }
        }
        return true;
    }

    private function sumExpensesForMonths(User $user, array $monthsAgo, bool $lastYear = false): float
    {
        $total = 0.0;

        foreach ($monthsAgo as $m) {
            $date = new \DateTime();
            if ($lastYear) {
                $date->modify("-1 year");
            }
            $date->modify("-$m months");

            $expenses = $user->getExpenses()->filter(fn($e) => $e->getDate()->format('Y-m') === $date->format('Y-m'));

            foreach ($expenses as $expense) {
                $total += $expense->getAmount();
            }
        }

        return $total;
    }
}
