<?php

declare(strict_types=1);

namespace App\Dto;

/**
 * @phpstan-type TopRow array{name:string, amount:float, user?:string|null}
 */
final class TopExpensesResult
{
    /**
     * @param array<int,TopRow> $rows
     */
    public function __construct(
        public readonly string $periodLabel,
        public readonly bool $showUser,
        public readonly array $rows
    ) {}
}
