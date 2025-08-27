<?php

declare(strict_types=1);

namespace App\Facades;

use App\Diagrams\Calculators\TopExpensesCalculator;
use App\Diagrams\Generators\TopExpensesGenerator;
use App\Entity\User;
use Psr\Log\LoggerInterface;

final class TopExpensesFacade
{
    public function __construct(
        private readonly TopExpensesCalculator $calculator,
        private readonly TopExpensesGenerator $generator
    ) {}

    /**
     * Backward-compatible signature with the old service.
     *
     * @param string      $option        'user' | 'family'
     * @param User        $user
     * @param string|null $selectedMonth 'YYYY-MM' (defaults to current month)
     */
    public function generateSvg(string $option, User $user, ?string $selectedMonth = null, LoggerInterface $logger): string
    {
        [$monthRef, $label] = $this->calculator->resolveSelectedMonth($selectedMonth);

        if ($option === 'family' && $user->getFamily()) {
            $family = $user->getFamily();
            $users = $family?->getUsers();
            if (!$users || count($users) === 0) {
                // Special-case unchanged from previous behavior
                return $this->generator->noDataSvg('No family members');
            }

            $result = $this->calculator->calculateForFamily($family, $monthRef, $label);
            $logger->info("The top expenses for the family {familyName} are: " . json_encode($result), ['familyName' => $family->getName()]);
            return $this->generator->generateSvg($result);
        }

        $result = $this->calculator->calculateForUser($user, $monthRef, $label);
        $logger->info("The top expenses for user {userEmail} are: " . json_encode($result), ['userEmail' => $user->getEmail()]);
        return $this->generator->generateSvg($result);
    }
}
