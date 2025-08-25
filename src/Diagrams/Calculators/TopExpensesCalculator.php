<?php

declare(strict_types=1);

namespace App\Diagrams\Calculators;

use App\Dto\TopExpensesResult;
use App\Entity\Expense;
use App\Entity\Family;
use App\Entity\User;

final class TopExpensesCalculator
{
    /**
     * Normalize selected month (YYYY-MM) to a DateTime (first day of month) and a label.
     *
     * @return array{0:\DateTime,1:string}
     */
    public function resolveSelectedMonth(?string $selectedMonth): array
    {
        if ($selectedMonth && preg_match('/^\d{4}-\d{2}$/', $selectedMonth) === 1) {
            $firstDay = \DateTime::createFromFormat('Y-m-d H:i:s', $selectedMonth . '-01 00:00:00')
                ?: new \DateTime('first day of this month');
        } else {
            $firstDay = new \DateTime('first day of this month');
        }
        $label = $firstDay->format('M');
        return [$firstDay, $label];
    }

    public function calculateForUser(User $user, \DateTime $monthRef, string $label): TopExpensesResult
    {
        $rows = [];

        foreach ($user->getExpenses() as $expense) {
            if (!$expense instanceof Expense) {
                continue;
            }
            $date = $expense->getDate();
            if (!$date || $date->format('Y-m') !== $monthRef->format('Y-m')) {
                continue;
            }
            $rows[] = [
                'amount' => (float) $expense->getAmount(),
                'name'   => (string) $expense->getName(),
                'user'   => null,
            ];
        }

        $rows = $this->sortAndTakeTop($rows, 5);

        return new TopExpensesResult(
            periodLabel: $label,
            showUser: false,
            rows: $rows
        );
    }

    public function calculateForFamily(Family $family, \DateTime $monthRef, string $label): TopExpensesResult
    {
        $rows = [];

        $users = $family->getUsers();
        foreach ($users as $u) {
            if (!$u instanceof User) {
                continue;
            }
            $displayUser = $this->displayUserName($u);

            foreach ($u->getExpenses() as $expense) {
                if (!$expense instanceof Expense) {
                    continue;
                }
                $date = $expense->getDate();
                if (!$date || $date->format('Y-m') !== $monthRef->format('Y-m')) {
                    continue;
                }
                $rows[] = [
                    'amount' => (float) $expense->getAmount(),
                    'name'   => (string) $expense->getName(),
                    'user'   => $displayUser,
                ];
            }
        }

        $rows = $this->sortAndTakeTop($rows, 5);

        return new TopExpensesResult(
            periodLabel: $label,
            showUser: true,
            rows: $rows
        );
    }

    /**
     * @param array<int,array{name:string,amount:float,user?:string|null}> $rows
     * @return array<int,array{name:string,amount:float,user?:string|null}>
     */
    private function sortAndTakeTop(array $rows, int $limit): array
    {
        usort($rows, static fn ($a, $b) => ($b['amount'] ?? 0) <=> ($a['amount'] ?? 0));
        return array_slice($rows, 0, $limit);
    }

    private function displayUserName(User $u): string
    {
        if (method_exists($u, 'getName') && $u->getName()) {
            return (string) $u->getName();
        }
        if (method_exists($u, 'getFullName') && $u->getFullName()) {
            return (string) $u->getFullName();
        }
        if (method_exists($u, 'getUserIdentifier') && $u->getUserIdentifier()) {
            return (string) $u->getUserIdentifier();
        }
        if (method_exists($u, 'getEmail') && $u->getEmail()) {
            return (string) $u->getEmail();
        }
        if (method_exists($u, 'getId')) {
            return 'User #' . (string) $u->getId();
        }
        return 'User';
    }
}
