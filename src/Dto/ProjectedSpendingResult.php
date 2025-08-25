<?php

declare(strict_types=1);

namespace App\Dto;

final class ProjectedSpendingResult
{
    /**
     * @param array<int,float> $cumCurrent
     */
    public function __construct(
        public readonly float $currentToDate,
        public readonly float $projectedTotal,
        public readonly float $growthRate,
        public readonly ?\DateTime $budgetHit,
        public readonly float $prevToDate,
        public readonly array $cumCurrent,
        public readonly int $compareIndex,
        public readonly int $daysInMonth,
        public readonly bool $isCurrentMonth
    ) {}
}

