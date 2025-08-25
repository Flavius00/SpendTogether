<?php

declare(strict_types=1);

namespace App\Facade;

use App\Diagrams\Calculators\SelectedMonthVsLastMonthCalculator;
use App\Diagrams\Generators\SelectedMonthVsLastMonthSvgGenerator;
use App\Entity\User;

class SelectedMonthVsLastMonthFacade
{
    public function generateSvg(User $user, string $selectedMonth, string $options): string
    {

        $calculator = new SelectedMonthVsLastMonthCalculator();
        if ($options === 'family' && $user->getFamily() !== null) {
            $selectedMonthData = $calculator->calculateFamilyDailyExpenses($user->getFamily(), $selectedMonth);
            $previousMonthData = $calculator->calculateFamilyDailyExpenses($user->getFamily(), $calculator->getPreviousMonth($selectedMonth));
        } else {
            $selectedMonthData = $calculator->calculateUserDailyExpenses($user, $selectedMonth);
            $previousMonthData = $calculator->calculateUserDailyExpenses($user, $calculator->getPreviousMonth($selectedMonth));
        }

        $chartGenerator = new SelectedMonthVsLastMonthSvgGenerator();
        return $chartGenerator->generateChart($selectedMonthData, $previousMonthData, $selectedMonth);
    }
}
