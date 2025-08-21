<?php

declare(strict_types=1);

namespace App\Controller\Service;

use App\Entity\Family;
use App\Entity\User;

class TotalPerMonthSvgService
{
    private const COLORS = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#34D399', '#F87171'];
    private const CENTER_X = 200;
    private const CENTER_Y = 260;
    private const RADIUS = 120;

    public function generateSvg(string $option, string $selectedDate, User $user): string
    {
        if ($option === 'user') {
            return $this->generateUserSvg($selectedDate, $user);
        } else {
            return $this->generateFamilySvg($selectedDate, $user->getFamily());
        }
    }

    public function generateUserSvg(string $selectedDate, User $user): string
    {
        $categoryTotals = $this->calculateUserCategoryTotals($user, $selectedDate);

        if (empty($categoryTotals)) {
            return $this->generateEmptyStateSvg('No expenses found');
        }

        $legendData = [];
        foreach ($categoryTotals as $category => $amount) {
            $legendData[] = [
                'label' => $category . ' - $' . number_format($amount, 2),
                'amount' => $amount
            ];
        }

        return $this->generatePieChartSvg($categoryTotals, array_sum($categoryTotals), $legendData);
    }

    public function generateFamilySvg(string $selectedDate, Family $family): string
    {
        $familyBudget = $family->getMonthlyTargetBudget();

        if ($familyBudget <= 0) {
            return $this->generateEmptyStateSvg('No family budget set');
        }

        $userExpenses = $this->calculateFamilyUserExpenses($family, $selectedDate);
        $totalSpent = array_sum($userExpenses);
        $remainingBudget = $familyBudget - $totalSpent;

        if ($remainingBudget > 0) {
            $userExpenses['Available'] = $remainingBudget;
        }

        if (empty($userExpenses)) {
            return $this->generateEmptyStateSvg('No expenses found');
        }

        $legendData = [];
        foreach ($userExpenses as $userName => $amount) {
            $percentage = ($amount / $familyBudget) * 100;
            $legendData[] = [
                'label' => $userName . ' - ' . round($percentage, 1) . '% ($' . number_format($amount, 2) . ')',
                'amount' => $amount
            ];
        }

        return $this->generatePieChartSvg($userExpenses, (float)$familyBudget, $legendData);
    }

    private function calculateUserCategoryTotals(User $user, string $selectedDate): array
    {
        $expenses = $user->getExpenses();
        $categoryTotals = [];

        $expenses = $expenses->filter(function($expense) use ($selectedDate) {
            $currentMonth = new \DateTime($selectedDate);
            return $expense->getDate()->format('Y-m') === $currentMonth->format('Y-m');
        })->toArray();

        foreach ($expenses as $expense) {
            $category = $expense->getCategory()->getName();
            if (!isset($categoryTotals[$category])) {
                $categoryTotals[$category] = 0;
            }
            $categoryTotals[$category] += $expense->getAmount();
        }

        return $categoryTotals;
    }

    private function calculateFamilyUserExpenses(Family $family, string $selectedDate): array
    {
        $users = $family->getUsers();
        $userExpenses = [];

        foreach ($users as $user) {
            $expenses = $user->getExpenses();
            $userTotal = 0;

            $expenses = $expenses->filter(function($expense) use ($selectedDate) {
                $currentMonth = new \DateTime($selectedDate);
                return $expense->getDate()->format('Y-m') === $currentMonth->format('Y-m');
            });

            foreach ($expenses as $expense) {
                $userTotal += $expense->getAmount();
            }

            if ($userTotal > 0) {
                $userExpenses[$user->getName()] = $userTotal;
            }
        }

        return $userExpenses;
    }

    private function generateEmptyStateSvg(string $message): string
    {
        return '<svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">
            <text x="200" y="200" text-anchor="middle" dominant-baseline="middle" fill="#9CA3AF">' . htmlspecialchars($message) . '</text>
        </svg>';
    }

    private function generatePieChartSvg(array $data, float $total, array $legendData): string
    {
        $svg = '<svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">';
        $svg .= $this->generateLegend($legendData);

        if (count($data) === 1) {
            $svg .= $this->generateSingleSlice($data, $total);
        } else {
            $svg .= $this->generateMultipleSlices($data, $total);
        }

        $svg .= '</svg>';
        return $svg;
    }

    private function generateLegend(array $legendData): string
    {
        $legendContent = '';
        $legendX = 20;
        $legendY = 20;

        foreach ($legendData as $index => $item) {
            $color = self::COLORS[$index % count(self::COLORS)];
            $y = $legendY + ($index * 20);

            $legendContent .= '<rect x="' . $legendX . '" y="' . $y . '" width="12" height="12" fill="' . $color . '"/>';
            $legendContent .= '<text x="' . ($legendX + 20) . '" y="' . ($y + 10) . '" fill="#9CA3AF" font-size="12">' . htmlspecialchars($item['label']) . '</text>';
        }

        return $legendContent;
    }

    private function generateSingleSlice(array $data, float $total): string
    {
        $percentage = (current($data) / $total) * 100;
        $color = self::COLORS[0];

        return '<circle cx="' . self::CENTER_X . '" cy="' . self::CENTER_Y . '" r="' . self::RADIUS . '" fill="' . $color . '" stroke="white" stroke-width="2" />
            <text x="' . self::CENTER_X . '" y="' . self::CENTER_Y . '" text-anchor="middle" dominant-baseline="middle" fill="white" font-size="18" font-weight="bold">' . round($percentage, 1) . '%</text>';
    }

    private function generateMultipleSlices(array $data, float $total): string
    {
        $svg = '';
        $startAngle = 0;
        $colorIndex = 0;

        foreach ($data as $label => $amount) {
            $percentage = ($amount / $total) * 100;
            $angle = ($amount / $total) * 360;

            $x1 = self::CENTER_X + self::RADIUS * cos(deg2rad($startAngle));
            $y1 = self::CENTER_Y + self::RADIUS * sin(deg2rad($startAngle));
            $x2 = self::CENTER_X + self::RADIUS * cos(deg2rad($startAngle + $angle));
            $y2 = self::CENTER_Y + self::RADIUS * sin(deg2rad($startAngle + $angle));

            $largeArc = $angle > 180 ? 1 : 0;
            $pathData = "M " . self::CENTER_X . " " . self::CENTER_Y . " L $x1 $y1 A " . self::RADIUS . " " . self::RADIUS . " 0 $largeArc 1 $x2 $y2 Z";

            $color = self::COLORS[$colorIndex % count(self::COLORS)];
            $svg .= '<path d="' . $pathData . '" fill="' . $color . '" stroke="white" stroke-width="2"/>';

            if ($percentage > 5) {
                $labelAngle = $startAngle + ($angle / 2);
                $labelX = self::CENTER_X + (self::RADIUS * 0.6) * cos(deg2rad($labelAngle));
                $labelY = self::CENTER_Y + (self::RADIUS * 0.6) * sin(deg2rad($labelAngle));

                $svg .= '<text x="' . $labelX . '" y="' . $labelY . '" text-anchor="middle" dominant-baseline="middle" fill="white" font-size="12" font-weight="bold">' . round($percentage, 1) . '%</text>';
            }

            $startAngle += $angle;
            $colorIndex++;
        }

        return $svg;
    }
}
