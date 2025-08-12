<?php

namespace App\Controller\Service;

use App\Entity\Family;
use App\Entity\Threshold;

class ThresholdService
{
    /**
     * @param float $amount
     * @param array<Threshold> $thresholds
     * @param Family $family
     * @return bool
     */
    public function validateThresholdAmount(float $amount, array $thresholds, Family $family): bool
    {
        $sum = 0.0;

        foreach ($thresholds as $threshold) {
            $sum += (float)$threshold->getAmount();
        }

        if ($sum + $amount > (float)$family->getMonthlyTargetBudget()) {
            return false; // Total exceeds monthly budget
        }

        return true; // Valid amount
    }
}
