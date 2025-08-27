<?php

namespace App\Facades;

use App\Diagrams\Calculators\TotalPerMonthCalculator;
use App\Diagrams\Generators\TotalPerMonthSvgGenerator;
use App\Entity\User;
use Psr\Log\LoggerInterface;

class TotalPerMonthFacade
{
    public function generateSvg(User $user, string $selectedMonth, string $options, LoggerInterface $logger): string
    {
        $calculator = new TotalPerMonthCalculator();
        $svgGenerator = new TotalPerMonthSvgGenerator();

        if ($options === 'family' && $user->getFamily() !== null) {
            $data = $calculator->calculateFamilyUserExpenses($user->getFamily(), $selectedMonth);
            $familyBudget = $user->getFamily()->getMonthlyTargetBudget();
            $logger->info("The option is family and the budget is: " . $familyBudget);
            $logger->info("The total per month per user for the family {familyName} is: " . json_encode($data), ['familyName' => $user->getFamily()->getName()]);

            return $svgGenerator->generateSvg($data, $familyBudget);
        } else {
            $data = $calculator->calculateUserCategoryTotals($user, $selectedMonth);
            $logger->info("The option is user.");
            $logger->info("The total per month per category for user {userEmail} is: " . json_encode($data), ['userEmail' => $user->getEmail()]);

            return $svgGenerator->generateSvg($data);
        }
    }

}
