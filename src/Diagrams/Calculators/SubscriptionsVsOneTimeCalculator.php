<?php

declare(strict_types=1);

namespace App\Diagrams\Calculators;

use App\Dto\SubscriptionsVsOneTimeResult;
use App\Entity\Expense;

/**
 * Builds aggregation for a given months window from one or more expense iterators.
 */
final class SubscriptionsVsOneTimeCalculator
{
    /**
     * @param iterable[] $expensesIterators Doctrine collections or iterables of Expense
     */
    public function buildForIterators(array $expensesIterators, int $months): SubscriptionsVsOneTimeResult
    {
        $months = max(1, min(24, $months));

        // 1) Window and labels
        $monthsKeys = [];
        $labels = [];
        $now = new \DateTime('first day of this month');
        for ($i = $months - 1; $i >= 0; $i--) {
            $m = (clone $now)->modify("-$i months");
            $key = $m->format('Y-m');
            $monthsKeys[] = $key;
            $labels[$key] = $m->format('M'); // Month name only
        }

        // 2) Totals init
        $subsTotals = array_fill_keys($monthsKeys, 0.0);
        $oneTimeTotals = array_fill_keys($monthsKeys, 0.0);

        // 3) Aggregation boundaries
        $firstMonth = (clone $now)->modify('-' . ($months - 1) . ' months');
        $startBoundary = (clone $firstMonth)->setTime(0, 0, 0);
        $endBoundary = (clone $now)->modify('last day of this month')->setTime(23, 59, 59);

        foreach ($expensesIterators as $collection) {
            foreach ($collection as $expense) {
                if (!$expense instanceof Expense) {
                    continue;
                }
                $date = $expense->getDate();
                if (!$date || $date < $startBoundary || $date > $endBoundary) {
                    continue;
                }

                $key = $date->format('Y-m');
                if (!isset($subsTotals[$key])) {
                    continue;
                }

                $amount = (float) $expense->getAmount();
                if ($expense->getSubscription() !== null) {
                    $subsTotals[$key] += $amount;
                } else {
                    $oneTimeTotals[$key] += $amount;
                }
            }
        }

        // 4) Max
        $maxSubs = empty($subsTotals) ? 0.0 : max($subsTotals);
        $maxOne = empty($oneTimeTotals) ? 0.0 : max($oneTimeTotals);
        $maxVal = max($maxSubs, $maxOne);

        return new SubscriptionsVsOneTimeResult(
            months: $months,
            monthsKeys: $monthsKeys,
            labels: $labels,
            subsTotals: $subsTotals,
            oneTimeTotals: $oneTimeTotals,
            maxVal: $maxVal
        );
    }
}
