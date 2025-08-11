<?php

namespace App\Controller\Service;

use App\Entity\Family;

class ThresholdService
{
    public function validateThresholdAmount(float $amount, array $thresholds, Family $family): bool
    {
        $sum = 0;

        foreach ($thresholds as $threshold) {
            $sum += $threshold->getAmount();
        }

        if ($sum + $amount > $family->getMonthlyTargetBudget()) {
            return false; // Total exceeds monthly budget
        }

        return true; // Valid amount
    }
}
