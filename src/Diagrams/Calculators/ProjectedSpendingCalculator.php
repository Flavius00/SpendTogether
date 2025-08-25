<?php

namespace App\Diagrams\Calculators;

use App\Dto\ProjectedSpendingResult;

class ProjectedSpendingCalculator
{
    public function calculate(string $selectedMonth, array $dailyCurrent, array $dailyPrev, ?float $budget): ProjectedSpendingResult
    {
        [$monthStart, $monthEnd, $daysInMonth, $isCurrentMonth, $todayIndex] = $this->resolveMonthContext($selectedMonth);

        $cumCurrent = $this->cumulative($dailyCurrent);
        $cumPrev = $this->cumulative($dailyPrev);

        $compareIndex = $isCurrentMonth ? $todayIndex : $daysInMonth;
        $prevDays = count($dailyPrev);
        $prevCompareIndex = min($compareIndex, $prevDays);

        $currentToDate = $cumCurrent[$compareIndex] ?? 0.0;
        $prevToDate = $cumPrev[$prevCompareIndex] ?? 0.0;
        $prevTotal = end($cumPrev) ?: 0.0;

        $growthRate = $this->computeGrowthRate($currentToDate, $prevToDate);

        $projectedTotal = $this->computeProjectedTotal($currentToDate, $compareIndex, $daysInMonth, $prevTotal, $growthRate);

        $budgetHit = $this->computeBudgetHitDate($cumCurrent, $compareIndex, $daysInMonth, $projectedTotal, $budget, $monthStart);

        return new ProjectedSpendingResult(
            $currentToDate,
            $projectedTotal,
            $growthRate,
            $budgetHit,
            $prevToDate,
            $cumCurrent,
            $compareIndex,
            $daysInMonth,
            $isCurrentMonth
        );
    }

    public function resolveMonthContext(string $selectedMonth): array
    {
        $firstDay = \DateTime::createFromFormat('Y-m-d H:i:s', $selectedMonth . '-01 00:00:00')
            ?: new \DateTime('first day of this month');
        $monthStart = (clone $firstDay)->setTime(0, 0, 0);
        $monthEnd = (clone $firstDay)->modify('last day of this month')->setTime(23, 59, 59);
        $daysInMonth = (int) $firstDay->format('t');

        $isCurrentMonth = ($firstDay->format('Y-m') === (new \DateTime())->format('Y-m'));
        $todayIndex = $isCurrentMonth ? (int) (new \DateTime())->format('j') : $daysInMonth;

        return [$monthStart, $monthEnd, $daysInMonth, $isCurrentMonth, $todayIndex];
    }

    public function cumulative(array $daily): array
    {
        $sum = 0.0;
        $cum = [];
        foreach ($daily as $day => $val) {
            $sum += (float) $val;
            $cum[$day] = $sum;
        }
        return $cum;
    }

    public function computeGrowthRate(float $currentToDate, float $prevToDate): float
    {
        if ($prevToDate > 0.0) {
            return max(0.0, $currentToDate / $prevToDate);
        }
        // Fallback: if previous month has no data up to this day,
        // use neutral rate 1.0 if there were expenses in current month, else 0.
        return $currentToDate > 0.0 ? 1.0 : 0.0;
    }

    public function computeProjectedTotal(
        float $currentToDate,
        int $compareIndex,
        int $daysInMonth,
        float $prevTotal,
        float $growthRate
    ): float
    {
        // Primary: project from last month's total scaled by growth rate
        $projection = $prevTotal * $growthRate;

        // Fallback: linear pace extrapolation if primary is zero/very low
        if ($projection <= 0.0 && $compareIndex > 0) {
            $dailyAvg = $currentToDate / $compareIndex;
            $projection = $dailyAvg * $daysInMonth;
        }

        // Never below what is already spent
        return max($currentToDate, $projection);
    }

    public function computeBudgetHitDate(
        array $cumCurrent,
        int $compareIndex,
        int $daysInMonth,
        float $projectedTotal,
        ?float $budget,
        \DateTime $monthStart
    ): ?\DateTime
    {
        if ($budget === null || $budget <= 0.0) {
            return null;
        }

        // 1) If the budget has already been exceeded, return the first actual day
        for ($d = 1; $d <= $compareIndex; $d++) {
            if (($cumCurrent[$d] ?? 0.0) >= $budget) {
                return (clone $monthStart)->modify('+' . ($d - 1) . ' days');
            }
        }

        // 2) Otherwise, estimate the day of exceeding based on the projected pace
        $currentToDate = $cumCurrent[$compareIndex] ?? 0.0;
        $remainingDays = max(0, $daysInMonth - $compareIndex);
        if ($remainingDays === 0) {
            return null;
        }

        $remainingNeeded = $budget - $currentToDate;
        $projRemaining = max(0.0, $projectedTotal - $currentToDate);
        if ($projRemaining <= 0.0) {
            return null;
        }

        $dailyProjected = $projRemaining / $remainingDays;
        if ($dailyProjected <= 0.0) {
            return null;
        }

        $daysNeeded = (int) ceil($remainingNeeded / $dailyProjected);
        $hitIndex = $compareIndex + $daysNeeded;
        if ($hitIndex > $daysInMonth) {
            return null; // not reached in the current month at the current pace
        }

        return (clone $monthStart)->modify('+' . ($hitIndex - 1) . ' days');
    }
}
