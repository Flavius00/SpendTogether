<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * Holds precomputed data for the Subscriptions vs One-time chart.
 *
 * @phpstan-type Totals array<string,float>
 * @phpstan-type Labels array<string,string>
 */
final class SubscriptionsVsOneTimeResult
{
    /**
     * @param string[] $monthsKeys
     * @param Labels   $labels
     * @param Totals   $subsTotals
     * @param Totals   $oneTimeTotals
     */
    public function __construct(
        public readonly int $months,
        public readonly array $monthsKeys,
        public readonly array $labels,
        public readonly array $subsTotals,
        public readonly array $oneTimeTotals,
        public readonly float $maxVal
    ) {}
}
