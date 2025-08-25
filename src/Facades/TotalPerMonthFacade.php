<?php

namespace App\Facades;

use App\Diagrams\Calculators\TotalPerMonthCalculator;
use App\Diagrams\Generators\TotalPerMonthSvgGenerator;
use App\Entity\User;

class TotalPerMonthFacade
{
    public function generateSvg(User $user, string $selectedMonth, string $options): string
    {
        $calculator = new TotalPerMonthCalculator();
        $svgGenerator = new TotalPerMonthSvgGenerator();

        if ($options === 'family' && $user->getFamily() !== null) {
            $data = $calculator->calculateFamilyUserExpenses($user->getFamily(), $selectedMonth);
            $familyBudget = $user->getFamily()->getMonthlyTargetBudget();

            return $svgGenerator->generateSvg($data, $familyBudget);
        } else {
            $data = $calculator->calculateUserCategoryTotals($user, $selectedMonth);

            return $svgGenerator->generateSvg($data);
        }
    }

}
