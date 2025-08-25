<?php

declare(strict_types=1);

namespace App\Facades;

use App\Diagrams\Calculators\SubscriptionsVsOneTimeCalculator;
use App\Diagrams\Generators\SubscriptionsVsOneTimeGenerator;
use App\Entity\Family;
use App\Entity\User;

final class SubscriptionsVsOneTimeFacade
{
    public function __construct(
        private readonly SubscriptionsVsOneTimeCalculator $calculator,
        private readonly SubscriptionsVsOneTimeGenerator $generator
    ) {}

    public function generateSvg(string $option, User $user): string
    {
        return $this->generateSvgForLastMonths(12, $option, $user);
    }

    public function generateSvgForLastMonths(int $months, string $option, User $user): string
    {
        $months = max(1, min(24, $months));

        if ($option === 'family' && $user->getFamily()) {
            return $this->generateFamilySvgMonths($user->getFamily(), $months);
        }

        return $this->generateUserSvgMonths($user, $months);
    }

    public function generateUserSvg(User $user): string
    {
        return $this->generateUserSvgMonths($user, 12);
    }

    public function generateFamilySvg(Family $family): string
    {
        return $this->generateFamilySvgMonths($family, 12);
    }

    private function generateUserSvgMonths(User $user, int $months): string
    {
        $expenses = $user->getExpenses();
        $result = $this->calculator->buildForIterators([$expenses], $months);

        if ($result->maxVal <= 0.0) {
            return $this->generator->noDataSvg("No data found for the last {$result->months} months");
        }

        return $this->generator->generateSvg($result);
    }

    private function generateFamilySvgMonths(Family $family, int $months): string
    {
        $users = $family->getUsers();
        if (!$users || count($users) === 0) {
            return $this->generator->noDataSvg('No family members');
        }

        $iterators = [];
        foreach ($users as $u) {
            $iterators[] = $u->getExpenses();
        }

        $result = $this->calculator->buildForIterators($iterators, $months);

        if ($result->maxVal <= 0.0) {
            return $this->generator->noDataSvg("No data found for the last {$result->months} months");
        }

        return $this->generator->generateSvg($result);
    }
}
