<?php

declare(strict_types=1);

namespace App\Controller\Service;

use App\Entity\Family;
use App\Entity\Threshold;

class ThresholdService
{
    /**
     * @param array<Threshold> $thresholds
     */
    public function validateThresholdAmount(float $amount, array $thresholds, Family $family): bool
    {
        $sum = 0.0;

        foreach ($thresholds as $threshold) {
            $sum += (float) $threshold->getAmount();
        }

        if ($sum + $amount > (float) $family->getMonthlyTargetBudget()) {
            return false; // Total exceeds monthly budget
        }

        return true; // Valid amount
    }
}
