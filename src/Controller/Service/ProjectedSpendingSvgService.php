<?php

declare(strict_types=1);

namespace App\Controller\Service;

use App\Entity\Expense;
use App\Entity\User;
use App\Entity\Family;

final class ProjectedSpendingSvgService
{
    public function generateSvg(string $option, User $user, string $selectedMonth): string
    {
        [$monthStart, $monthEnd, $daysInMonth, $isCurrentMonth, $todayIndex] = $this->resolveMonthContext($selectedMonth);

        // Collect expenses for current month (user or family)
        $dailyCurrent = array_fill(1, $daysInMonth, 0.0);

        if ($option === 'family' && $user->getFamily()) {
            $this->accumulateFamilyMonthExpenses($user->getFamily(), $dailyCurrent, $monthStart, $monthEnd);
        } else {
            $this->accumulateUserMonthExpenses($user, $dailyCurrent, $monthStart, $monthEnd);
        }

        // Build cumulative current
        $cumCurrent = $this->cumulative($dailyCurrent);

        // Previous month reference window
        $prevStart = (clone $monthStart)->modify('first day of previous month')->setTime(0, 0, 0);
        $prevEnd   = (clone $prevStart)->modify('last day of this month')->setTime(23, 59, 59);
        $prevDays  = (int) $prevStart->format('t');

        $dailyPrev = array_fill(1, $prevDays, 0.0);
        if ($option === 'family' && $user->getFamily()) {
            $this->accumulateFamilyMonthExpenses($user->getFamily(), $dailyPrev, $prevStart, $prevEnd);
        } else {
            $this->accumulateUserMonthExpenses($user, $dailyPrev, $prevStart, $prevEnd);
        }
        $cumPrev = $this->cumulative($dailyPrev);

        // Define "to date" day index for comparison
        $compareIndex = $isCurrentMonth ? $todayIndex : $daysInMonth;
        $prevCompareIndex = min($compareIndex, $prevDays);

        $currentToDate = $cumCurrent[$compareIndex] ?? 0.0;
        $prevToDate = $cumPrev[$prevCompareIndex] ?? 0.0;
        $prevTotal = $cumPrev[$prevDays] ?? 0.0;

        // Growth rate vs last month-to-date (ratio)
        $growthRate = $this->computeGrowthRate($currentToDate, $prevToDate);

        // Projected end-of-month total
        $projectedTotal = $this->computeProjectedTotal(
            $currentToDate,
            $compareIndex,
            $daysInMonth,
            $prevTotal,
            $growthRate
        );

        // Budget line (only meaningful for family option)
        $budget = null;
        if ($option === 'family' && $user->getFamily()) {
            $familyBudget = $user->getFamily()->getMonthlyTargetBudget();
            if ($familyBudget !== null && (float) $familyBudget > 0) {
                $budget = (float) $familyBudget;
            }
        }

        // Budget hit: first actual day of exceeding or an estimate if not yet exceeded
        $budgetHit = $this->computeBudgetHitDate(
            $cumCurrent,
            $compareIndex,
            $daysInMonth,
            $projectedTotal,
            $budget,
            $monthStart
        );

        // Build chart SVG
        return $this->buildSvgChart(
            $selectedMonth,
            $daysInMonth,
            $cumCurrent,
            $compareIndex,
            $projectedTotal,
            $budget,
            $budgetHit,
            $isCurrentMonth,
            $currentToDate,
            $prevToDate
        );
    }

    private function resolveMonthContext(string $selectedMonth): array
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

    private function accumulateUserMonthExpenses(User $user, array &$daily, \DateTime $start, \DateTime $end): void
    {
        foreach ($user->getExpenses() as $expense) {
            if (!$expense instanceof Expense) {
                continue;
            }
            $d = $expense->getDate();
            if (!$d || $d < $start || $d > $end) {
                continue;
            }
            $dayIdx = (int) $d->format('j');
            if (isset($daily[$dayIdx])) {
                $daily[$dayIdx] += (float) $expense->getAmount();
            }
        }
    }

    private function accumulateFamilyMonthExpenses(Family $family, array &$daily, \DateTime $start, \DateTime $end): void
    {
        $users = $family->getUsers();
        foreach ($users as $u) {
            if (!$u instanceof User) {
                continue;
            }
            $this->accumulateUserMonthExpenses($u, $daily, $start, $end);
        }
    }

    private function cumulative(array $daily): array
    {
        $sum = 0.0;
        $cum = [];
        foreach ($daily as $day => $val) {
            $sum += (float) $val;
            $cum[$day] = $sum;
        }
        return $cum;
    }

    private function computeGrowthRate(float $currentToDate, float $prevToDate): float
    {
        if ($prevToDate > 0.0) {
            return max(0.0, $currentToDate / $prevToDate);
        }
        // Fallback: if previous month has no data up to this day,
        // use neutral rate 1.0 if there were expenses in current month, else 0.
        return $currentToDate > 0.0 ? 1.0 : 0.0;
    }

    private function computeProjectedTotal(
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

    private function computeBudgetHitDate(
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

    private function buildSvgChart(
        string $selectedMonth,
        int $daysInMonth,
        array $cumCurrent,
        int $compareIndex,
        float $projectedTotal,
        ?float $budget,
        ?\DateTime $budgetHit,
        bool $isCurrentMonth,
        float $currentToDate,
        float $prevToDate
    ): string
    {
        $vbW = 800;
        $vbH = 260;

        // Header above the gridlines
        $margin = [
            'top' => 64,
            'right' => 24,
            'bottom' => 56,
            'left' => 64,
        ];

        $chartW = $vbW - $margin['left'] - $margin['right'];
        $chartH = $vbH - $margin['top'] - $margin['bottom'];

        $axisColor = '#334155';
        $gridColor = '#334155';
        $labelColor = '#E5E7EB';
        $actualColor = '#22C55E';
        $projectionColor = '#9CA3AF';
        $budgetColor = '#EF4444';

        // Y max includes projected total and budget if present, plus headroom
        $maxY = max(
            $projectedTotal,
            $budget ?? 0.0,
            $cumCurrent[$compareIndex] ?? 0.0
        );
        if ($maxY <= 0) {
            $maxY = 1.0;
        }
        $maxY *= 1.1; // headroom
        $scaleY = $chartH / $maxY;

        $mapX = static function (int $day) use ($daysInMonth, $chartW, $margin): float {
            if ($daysInMonth <= 1) {
                return (float) $margin['left'];
            }
            $t = ($day - 1) / ($daysInMonth - 1);
            return $margin['left'] + $t * $chartW;
        };
        $mapY = static function (float $val) use ($chartH, $margin, $scaleY): float {
            return $margin['top'] + $chartH - ($val * $scaleY);
        };

        $svg = [];
        $svg[] = '<svg viewBox="0 0 ' . $vbW . ' ' . $vbH . '" width="100%" height="100%" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" role="img" style="display:block" aria-label="Projected current month spending for ' . htmlspecialchars($selectedMonth) . '">';

        // Title and meta (include "Spent to date", "Projected", and a separate line for growth with arrow and color)
        $title = 'Spending Projection - ' . $selectedMonth;

        $subParts = [];
        $subParts[] = 'Spent to date: ' . number_format($currentToDate, 0);
        $subParts[] = 'Projected total: ' . number_format($projectedTotal, 0);

        // Move growth to a separate colored line
        if ($budget !== null) {
            $budgetInfo = 'Budget: ' . number_format($budget, 0);
            $budgetInfo .= ' | Budget hit: ' . ($budgetHit ? $budgetHit->format('M j') : 'n/a');
            $subParts[] = $budgetInfo;
        }
        $sub = implode(' | ', $subParts);

        $svg[] = '<text x="' . $margin['left'] . '" y="18" fill="' . $labelColor . '" font-size="18" font-weight="600">' . htmlspecialchars($title) . '</text>';
        $svg[] = '<text x="' . $margin['left'] . '" y="36" fill="' . $labelColor . '" font-size="12">' . htmlspecialchars($sub) . '</text>';

        // Colored line for Growth
        if ($prevToDate > 0.0) {
            $growthPct = ($currentToDate / $prevToDate - 1.0) * 100.0;
            $arrow = $growthPct >= 0 ? '▲' : '▼';
            $growthColor = $growthPct >= 0 ? $actualColor : $budgetColor;
            $growthFormatted = ($growthPct >= 0 ? '+' : '') . number_format($growthPct, 1) . '%';

            $svg[] = '<text x="' . $margin['left'] . '" y="52" fill="' . $labelColor . '" font-size="12">Growth vs last month to-date: <tspan fill="' . $growthColor . '" font-weight="600">' . $arrow . ' ' . $growthFormatted . '</tspan></text>';
        } else {
            $svg[] = '<text x="' . $margin['left'] . '" y="52" fill="' . $labelColor . '" font-size="12">Growth vs last month to-date: n/a</text>';
        }

        // Gridlines and Y ticks (4 lines)
        $gridLines = 4;
        for ($i = 0; $i <= $gridLines; $i++) {
            $val = $maxY * ($i / $gridLines);
            $y = $mapY($val);
            $svg[] = '<line x1="' . $margin['left'] . '" y1="' . $y . '" x2="' . ($vbW - $margin['right']) . '" y2="' . $y . '" stroke="' . $gridColor . '" stroke-width="1" />';
            $label = $val >= 1000 ? number_format($val / 1000, 1) . 'k' : number_format($val, 0);
            $svg[] = '<text x="' . ($margin['left'] - 10) . '" y="' . $y . '" fill="' . $labelColor . '" font-size="12" text-anchor="end" dominant-baseline="middle">' . $label . '</text>';
        }

        // Axes
        $xAxisY = $margin['top'] + $chartH;
        $svg[] = '<line x1="' . $margin['left'] . '" y1="' . $xAxisY . '" x2="' . ($vbW - $margin['right']) . '" y2="' . $xAxisY . '" stroke="' . $axisColor . '" stroke-width="1" />';
        $svg[] = '<line x1="' . $margin['left'] . '" y1="' . $margin['top'] . '" x2="' . $margin['left'] . '" y2="' . ($margin['top'] + $chartH) . '" stroke="' . $axisColor . '" stroke-width="1" />';

        // X labels (sparse: every ~5th day + last)
        $tickEvery = max(1, (int) ceil($daysInMonth / 6));
        for ($d = 1; $d <= $daysInMonth; $d++) {
            if ($d % $tickEvery === 0 || $d === 1 || $d === $daysInMonth) {
                $x = $mapX($d);
                $svg[] = '<text x="' . $x . '" y="' . ($xAxisY + 16) . '" fill="' . $labelColor . '" font-size="12" text-anchor="middle">' . $d . '</text>';
            }
        }

        // Budget line
        if ($budget !== null) {
            $yBudget = $mapY($budget);
            $svg[] = '<line x1="' . $margin['left'] . '" y1="' . $yBudget . '" x2="' . ($vbW - $margin['right']) . '" y2="' . $yBudget . '" stroke="' . $budgetColor . '" stroke-width="2" stroke-dasharray="4,4" />';
            $svg[] = '<text x="' . ($vbW - $margin['right']) . '" y="' . ($yBudget - 6) . '" fill="' . $budgetColor . '" font-size="12" text-anchor="end">Family budget</text>';
        }

        // Actual line (solid green) from day 1 to compareIndex
        $pathActual = '';
        for ($d = 1; $d <= $compareIndex; $d++) {
            $x = $mapX($d);
            $y = $mapY($cumCurrent[$d] ?? 0.0);
            $pathActual .= ($d === 1 ? 'M' : 'L') . $x . ' ' . $y . ' ';
        }
        if ($pathActual !== '') {
            $svg[] = '<path d="' . trim($pathActual) . '" fill="none" stroke="' . $actualColor . '" stroke-width="3" />';
        }

        // Projection line (dotted gray) from compareIndex to last day, linear interpolation to projectedTotal
        if ($compareIndex < $daysInMonth) {
            $x0 = $mapX($compareIndex);
            $y0 = $mapY($cumCurrent[$compareIndex] ?? 0.0);
            $x1 = $mapX($daysInMonth);
            $y1 = $mapY($projectedTotal);
            $svg[] = '<path d="M' . $x0 . ' ' . $y0 . ' L' . $x1 . ' ' . $y1 . '" fill="none" stroke="' . $projectionColor . '" stroke-width="3" stroke-dasharray="6,6" />';
        }

        // Markers at today and end
        $r = 3.5;
        $xToday = $mapX($compareIndex);
        $yToday = $mapY($cumCurrent[$compareIndex] ?? 0.0);
        $svg[] = '<circle cx="' . $xToday . '" cy="' . $yToday . '" r="' . $r . '" fill="' . $actualColor . '" />';
        $xEnd = $mapX($daysInMonth);
        $yEnd = $mapY($projectedTotal);
        $svg[] = '<circle cx="' . $xEnd . '" cy="' . $yEnd . '" r="' . $r . '" fill="' . $projectionColor . '" />';

        // Label next to the "today" marker: amount spent so far
        $svg[] = '<text x="' . ($xToday + 6) . '" y="' . ($yToday - 8) . '" fill="' . $labelColor . '" font-size="12" dominant-baseline="ideographic">To date: ' . number_format($currentToDate, 0) . '</text>';

        // Legend
        $legendX = $margin['left'];
        $legendY = $vbH - $margin['bottom'] + 28;
        $svg[] = '<rect x="' . $legendX . '" y="' . ($legendY - 10) . '" width="14" height="3" fill="' . $actualColor . '"/>';
        $svg[] = '<text x="' . ($legendX + 20) . '" y="' . $legendY . '" fill="' . $labelColor . '" font-size="12">Actual</text>';
        $svg[] = '<rect x="' . ($legendX + 80) . '" y="' . ($legendY - 10) . '" width="14" height="3" fill="' . $projectionColor . '" />';
        $svg[] = '<text x="' . ($legendX + 100) . '" y="' . $legendY . '" fill="' . $labelColor . '" font-size="12">Projected</text>';
        if ($budget !== null) {
            $svg[] = '<rect x="' . ($legendX + 190) . '" y="' . ($legendY - 10) . '" width="14" height="3" fill="' . $budgetColor . '" />';
            $svg[] = '<text x="' . ($legendX + 210) . '" y="' . $legendY . '" fill="' . $labelColor . '" font-size="12">Budget</text>';
        }

        $svg[] = '</svg>';
        return implode('', $svg);
    }
}
