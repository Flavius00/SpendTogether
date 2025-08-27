<?php

declare(strict_types=1);

namespace App\Message;

use Symfony\Component\Messenger\Attribute\AsMessage;

#[AsMessage('async')]
final class BudgetWarningEmailMessage
{
    public function __construct(
        public readonly int $familyId,
        public readonly int $userId,
        public readonly string $month, // Y-m
        public readonly float $projectedTotal,
        public readonly float $budget,
        public readonly string $type = 'family_budget', // 'family_budget' | 'category_threshold'
        public readonly ?int $categoryId = null
    ) {}
}
