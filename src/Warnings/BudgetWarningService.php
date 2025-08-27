<?php

declare(strict_types=1);

namespace App\Warnings;

use App\Diagrams\Calculators\ProjectedSpendingCalculator;
use App\Entity\Expense;
use App\Entity\Family;
use App\Entity\User;
use App\Entity\Category;
use App\Entity\Threshold;
use App\Repository\BudgetAlertLogRepository;
use App\Repository\ThresholdsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

final class BudgetWarningService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly ProjectedSpendingCalculator $projectedCalculator,
        private readonly BudgetAlertLogRepository $alertRepo,
        private readonly MessageBusInterface $bus,
        private readonly ThresholdsRepository $thresholdsRepo
    ) {
    }

    /**
     * Return warning context if family's projection exceeds budget for current month.
     * ['exceeds'=>bool,'month'=>string,'projected'=>float,'budget'=>float,'_monthKey'=>string]
     */
    public function computeFamilyBudgetWarning(User $admin): ?array
    {
        $family = $admin->getFamily();
        if (!$family instanceof Family) {
            return null;
        }
        $budgetStr = $family->getMonthlyTargetBudget();
        $budget = $budgetStr !== null ? (float) $budgetStr : 0.0;
        if ($budget <= 0.0) {
            return null;
        }

        $selectedMonth = (new \DateTime('first day of this month'))->format('Y-m');
        [$monthStart, $monthEnd, $daysInMonth] = $this->projectedCalculator->resolveMonthContext($selectedMonth);

        $dailyCurrent = array_fill(1, (int) $daysInMonth, 0.0);
        $this->accumulateFamilyMonthExpenses($family, $dailyCurrent, $monthStart, $monthEnd);

        $prevStart = (clone $monthStart)->modify('first day of previous month')->setTime(0, 0, 0);
        $prevEnd   = (clone $prevStart)->modify('last day of this month')->setTime(23, 59, 59);
        $prevDays  = (int) $prevStart->format('t');
        $dailyPrev = array_fill(1, $prevDays, 0.0);
        $this->accumulateFamilyMonthExpenses($family, $dailyPrev, $prevStart, $prevEnd);

        $proj = $this->projectedCalculator->calculate($selectedMonth, $dailyCurrent, $dailyPrev, $budget);

        return [
            'exceeds'   => $proj->projectedTotal > $budget,
            'month'     => (new \DateTime($selectedMonth.'-01'))->format('F Y'),
            'projected' => $proj->projectedTotal,
            'budget'    => $budget,
            '_monthKey' => $selectedMonth,
        ];
    }

    public function enqueueBudgetWarningEmails(User $admin, array $warning): void
    {
        if (($warning['exceeds'] ?? false) !== true) {
            return;
        }
        $family = $admin->getFamily();
        if (!$family instanceof Family) {
            return;
        }
        $monthKey = (string) ($warning['_monthKey'] ?? (new \DateTime())->format('Y-m'));
        $projected = (float) $warning['projected'];
        $budget = (float) $warning['budget'];

        if ($this->alertRepo->existsForFamilyMonthAmount($family, 'family_budget', $monthKey, $projected)) {
            return;
        }

        foreach ($family->getUsers() as $member) {
            if (!$member instanceof User) {
                continue;
            }
            $this->bus->dispatch(
                new \App\Message\BudgetWarningEmailMessage(
                    familyId: (int) $family->getId(),
                    userId: (int) $member->getId(),
                    month: $monthKey,
                    projectedTotal: $projected,
                    budget: $budget,
                    type: 'family_budget',
                    categoryId: null
                )
            );
        }
    }

    /**
     * Returnează depășirile pe categorie pentru luna curentă.
     * Fiecare element: ['category'=>Category,'current'=>float,'limit'=>float,'monthKey'=>string]
     */
    public function computeCategoryThresholdBreaches(User $admin): array
    {
        $family = $admin->getFamily();
        if (!$family instanceof Family) {
            return [];
        }

        $monthKey   = (new \DateTime('first day of this month'))->format('Y-m');
        $monthStart = (new \DateTimeImmutable($monthKey . '-01 00:00:00'));
        $monthEnd   = $monthStart->modify('last day of this month')->setTime(23, 59, 59);

        // Praguri definite pentru familie (Category <-> Family)
        $thresholds = $this->thresholdsRepo->findBy(['family' => $family]);
        if ($thresholds === []) {
            return [];
        }

        // Suma curentă pe categorie în această lună
        $currentPerCategory = [];
        foreach ($family->getUsers() as $u) {
            if (!$u instanceof User) {
                continue;
            }
            foreach ($u->getExpenses() as $expense) {
                $d = $expense->getDate();
                $cat = $expense->getCategory();
                if (!$d || !$cat instanceof Category) {
                    continue;
                }
                if ($d < $monthStart || $d > $monthEnd) {
                    continue;
                }
                $cid = (int) $cat->getId();
                $currentPerCategory[$cid] = ($currentPerCategory[$cid] ?? 0.0) + (float) $expense->getAmount();
            }
        }

        $breaches = [];
        foreach ($thresholds as $t) {
            if (!$t instanceof Threshold) {
                continue;
            }
            $cat = $t->getCategory();
            if (!$cat instanceof Category) {
                continue;
            }
            $cid = (int) $cat->getId();
            $current = (float) ($currentPerCategory[$cid] ?? 0.0);
            $limit   = (float) ($t->getAmount() ?? '0');
            if ($limit > 0.0 && $current > $limit) {
                $breaches[] = [
                    'category' => $cat,
                    'current'  => $current,
                    'limit'    => $limit,
                    'monthKey' => $monthKey,
                ];
            }
        }

        return $breaches;
    }

    /**
     * Pune în coadă emailuri pentru depășirile pe categorie (idempotent prin BudgetAlertLog, cu category).
     */
    public function enqueueCategoryThresholdEmails(User $admin, array $breaches): void
    {
        $family = $admin->getFamily();
        if (!$family instanceof Family) {
            return;
        }

        foreach ($breaches as $b) {
            /** @var Category $category */
            $category = $b['category'];
            $current  = (float) $b['current'];
            $limit    = (float) $b['limit'];
            $monthKey = (string) $b['monthKey'];

            if ($this->alertRepo->existsForFamilyMonthAmountAndCategory($family, 'category_threshold', $monthKey, $current, $category)) {
                continue;
            }

            foreach ($family->getUsers() as $member) {
                if (!$member instanceof User) {
                    continue;
                }
                $this->bus->dispatch(
                    new \App\Message\BudgetWarningEmailMessage(
                        familyId: (int) $family->getId(),
                        userId: (int) $member->getId(),
                        month: $monthKey,
                        projectedTotal: $current, // cheltuială curentă
                        budget: $limit,           // limită categorie
                        type: 'category_threshold',
                        categoryId: (int) $category->getId()
                    )
                );
            }
        }
    }

    /**
     * @param array<int,float> $daily
     */
    private function accumulateFamilyMonthExpenses(Family $family, array &$daily, \DateTime $start, \DateTime $end): void
    {
        foreach ($family->getUsers() as $u) {
            if (!$u instanceof User) {
                continue;
            }
            foreach ($u->getExpenses() as $expense) {
                $d = $expense->getDate();
                if (!$d || $d < $start || $d > $end) {
                    continue;
                }
                $dayIdx = (int) $d->format('j');
                if (isset($daily[$dayIdx])) {
                    $daily[$dayIdx] += (float) $expense->getAmount();
                }
            }
        }
    }
}
