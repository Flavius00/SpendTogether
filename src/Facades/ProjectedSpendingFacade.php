<?php

declare(strict_types=1);

namespace App\Facades;

use App\Diagrams\Calculators\ProjectedNextMonthSpendingCalculator;
use App\Diagrams\Calculators\ProjectedSpendingCalculator;
use App\Diagrams\Generators\ProjectedSpendingGenerator;
use App\Entity\Expense;
use App\Entity\Family;
use App\Entity\User;
use Psr\Log\LoggerInterface;

final class ProjectedSpendingFacade
{
    public function __construct(
        private readonly ProjectedSpendingCalculator $calculator,
        private readonly ProjectedNextMonthSpendingCalculator $nextMonthCalculator,
        private readonly ProjectedSpendingGenerator $generator
    ) {}

    /**
     * Backward-compatible signature for the dashboard.
     */
    public function generateSvg(string $option, User $user, string $selectedMonth, string $typeOfPrediction, LoggerInterface $logger): string
    {
        if ($typeOfPrediction === 'next') {
            // Next month projection
            $nextMonth = (new \DateTime($selectedMonth . '-01'))->modify('first day of next month')->format('Y-m');
            $result = $this->nextMonthCalculator->calculate($user);
            $budget = null;
            if ($option === 'family' && $user->getFamily()) {
                $familyBudget = $user->getFamily()->getMonthlyTargetBudget();
                if ($familyBudget !== null && (float) $familyBudget > 0) {
                    $budget = (float) $familyBudget;
                    $result = $this->nextMonthCalculator->calculateFamilyTotal($user->getFamily());
                }
            }
            $logger->info("The projection for next month ({nextMonth}) for the family ({familyName}): " . json_encode($result), ['nextMonth' => $nextMonth, 'familyName' => $user->getFamily() ? $user->getFamily()->getName() : 'N/A']);
            return $this->generator->generateSvg($nextMonth, $result, $budget);
        }
        return $this->generate($option, $user, $selectedMonth, $logger);
    }

    public function generate(string $option, User $user, string $selectedMonth, LoggerInterface $logger): string
    {
        // Resolve month window and allocate arrays
        [$monthStart, $monthEnd, $daysInMonth] = $this->calculator->resolveMonthContext($selectedMonth);
        $monthStart = $monthStart instanceof \DateTime ? $monthStart : new \DateTime('first day of this month');
        $monthEnd = $monthEnd instanceof \DateTime ? $monthEnd : (new \DateTime('last day of this month'))->setTime(23,59,59);

        $dailyCurrent = array_fill(1, (int) $daysInMonth, 0.0);

        // Fill current month totals
        if ($option === 'family' && $user->getFamily()) {
            $this->accumulateFamilyMonthExpenses($user->getFamily(), $dailyCurrent, $monthStart, $monthEnd);
        } else {
            $this->accumulateUserMonthExpenses($user, $dailyCurrent, $monthStart, $monthEnd);
        }

        // Previous month window (same approach ca până acum)
        $prevStart = (clone $monthStart)->modify('first day of previous month')->setTime(0, 0, 0);
        $prevEnd   = (clone $prevStart)->modify('last day of this month')->setTime(23, 59, 59);
        $prevDays  = (int) $prevStart->format('t');

        $dailyPrev = array_fill(1, $prevDays, 0.0);
        if ($option === 'family' && $user->getFamily()) {
            $this->accumulateFamilyMonthExpenses($user->getFamily(), $dailyPrev, $prevStart, $prevEnd);
        } else {
            $this->accumulateUserMonthExpenses($user, $dailyPrev, $prevStart, $prevEnd);
        }

        // Budget (only meaningful for family)
        $budget = null;
        if ($option === 'family' && $user->getFamily()) {
            $familyBudget = $user->getFamily()->getMonthlyTargetBudget();
            if ($familyBudget !== null && (float) $familyBudget > 0) {
                $budget = (float) $familyBudget;
            }
        }

        // Calculate + Generate
        $result = $this->calculator->calculate($selectedMonth, $dailyCurrent, $dailyPrev, $budget);
        $logger->info("The projection for the month ({selectedMonth}) for the user ({userEmail}): " . json_encode($result), ['selectedMonth' => $selectedMonth, 'userEmail' => $user->getEmail()]);
        return $this->generator->generateSvg($selectedMonth, $result, $budget);
    }

    /**
     * Helpers identical logic-wise with the previous implementation.
     *
     * @param array<int,float> $daily
     */
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

    /**
     * @param array<int,float> $daily
     */
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
}
