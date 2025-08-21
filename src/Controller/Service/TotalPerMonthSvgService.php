<?php

declare(strict_types=1);

namespace App\Controller\Service;

use App\Entity\Family;
use App\Entity\User;

class TotalPerMonthSvgService
{
    public function generateSvg(string $option, User $user) : string
    {
        if ($option === 'user') {
            return $this->generateUserSvg($user);
        } else {
            return $this->generateFamilySvg($user->getFamily());
        }
    }

    public function generateUserSvg(User $user) : string
    {
        $expenses = $user->getExpenses();
        $categoryTotals = [];

        $expenses = $expenses->filter(function($expense) {
            // Filter for current month
            $currentMonth = new \DateTime();
            return $expense->getDate()->format('Y-m') === $currentMonth->format('Y-m');
        })->toArray();

        foreach ($expenses as $expense) {
            $category = $expense->getCategory()->getName();
            if (!isset($categoryTotals[$category])) {
                $categoryTotals[$category] = 0;
            }
            $categoryTotals[$category] += $expense->getAmount();
        }

        if (empty($categoryTotals)) {
            return '<svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">
                <text x="200" y="200" text-anchor="middle" dominant-baseline="middle" fill="#9CA3AF">No expenses found</text>
            </svg>';
        }

        $total = array_sum($categoryTotals);
        $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#FF6384', '#C9CBCF'];

        $legendX = 20;
        $legendY = 20;
        $legendContent = '';
        $colorIndex = 0;

        foreach ($categoryTotals as $category => $amount) {
            $color = $colors[$colorIndex % count($colors)];
            $legendContent .= '<rect x="' . $legendX . '" y="' . ($legendY + ($colorIndex * 20)) . '" width="12" height="12" fill="' . $color . '"/>';
            $legendContent .= '<text x="' . ($legendX + 20) . '" y="' . ($legendY + ($colorIndex * 20) + 10) . '" fill="#9CA3AF" font-size="12">' . htmlspecialchars($category) . ' - $' . number_format($amount, 2) . '</text>';
            $colorIndex++;
        }

        $svg = '<svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">';

        // Coordonatele centrului și raza ajustate
        $centerX = 200;
        $centerY = 260; // Am mutat centrul cercului mai jos pe axa Y
        $radius = 120;

        // Verificare pentru un singur element
        if (count($categoryTotals) === 1) {
            $category = key($categoryTotals);
            $amount = current($categoryTotals);
            $color = $colors[0];

            $svg .= '<circle cx="' . $centerX . '" cy="' . $centerY . '" r="' . $radius . '" fill="' . $color . '" stroke="white" stroke-width="2" />';
            $svg .= '<text x="' . $centerX . '" y="' . $centerY . '" text-anchor="middle" dominant-baseline="middle" fill="white" font-size="18" font-weight="bold">100%</text>';

        } else {
            // Cod pentru mai multe categorii
            $startAngle = 0;
            $colorIndex = 0;

            foreach ($categoryTotals as $category => $amount) {
                $percentage = ($amount / $total) * 100;
                $angle = ($amount / $total) * 360;

                $x1 = $centerX + $radius * cos(deg2rad($startAngle));
                $y1 = $centerY + $radius * sin(deg2rad($startAngle));
                $x2 = $centerX + $radius * cos(deg2rad($startAngle + $angle));
                $y2 = $centerY + $radius * sin(deg2rad($startAngle + $angle));

                $largeArc = $angle > 180 ? 1 : 0;
                $sweep = ($startAngle < 360 && $startAngle + $angle > 360) ? 1 : 0;

                $pathData = "M $centerX $centerY L $x1 $y1 A $radius $radius 0 $largeArc 1 $x2 $y2 Z";

                $svg .= '<path d="' . $pathData . '" fill="' . $colors[$colorIndex % count($colors)] . '" stroke="white" stroke-width="2"/>';

                // Add category label
                $labelAngle = $startAngle + ($angle / 2);
                $labelX = $centerX + ($radius * 0.6) * cos(deg2rad($labelAngle));
                $labelY = $centerY + ($radius * 0.6) * sin(deg2rad($labelAngle));

                if ($percentage > 5) {
                    $svg .= '<text x="' . $labelX . '" y="' . $labelY . '" text-anchor="middle" dominant-baseline="middle" fill="white" font-size="12" font-weight="bold">' . round($percentage, 1) . '%</text>';
                }

                $startAngle += $angle;
                $colorIndex++;
            }
        }

        $svg .= $legendContent;
        $svg .= '</svg>';

        return $svg;
    }

    public function generateFamilySvg(Family $family) : string
    {
        $users = $family->getUsers();
        $familyBudget = $family->getMonthlyTargetBudget();

        if ($familyBudget <= 0) {
            return '<svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">
            <text x="200" y="200" text-anchor="middle" dominant-baseline="middle" fill="#9CA3AF">No family budget set</text>
        </svg>';
        }

        $userExpenses = [];
        $totalSpent = 0;

        foreach ($users as $user) {
            $expenses = $user->getExpenses();
            $userTotal = 0;

            // Filter for current month
            $expenses = $expenses->filter(function($expense) {
                $currentMonth = new \DateTime();
                return $expense->getDate()->format('Y-m') === $currentMonth->format('Y-m');
            });

            foreach ($expenses as $expense) {
                $userTotal += $expense->getAmount();
            }

            if ($userTotal > 0) {
                $userExpenses[$user->getName()] = $userTotal;
                $totalSpent += $userTotal;
            }
        }

        $remainingBudget = $familyBudget - $totalSpent;
        if ($remainingBudget > 0) {
            $userExpenses['Available'] = $remainingBudget;
        }

        if (empty($userExpenses)) {
            return '<svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">
            <text x="200" y="200" text-anchor="middle" dominant-baseline="middle" fill="#9CA3AF">No expenses found</text>
        </svg>';
        }

        $colors = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#34D399', '#F87171'];

        $legendX = 20;
        $legendY = 20;
        $legendContent = '';
        $colorIndex = 0;

        foreach ($userExpenses as $userName => $amount) {
            $percentage = ($amount / $familyBudget) * 100;
            $color = $colors[$colorIndex % count($colors)];
            $legendContent .= '<rect x="' . $legendX . '" y="' . ($legendY + ($colorIndex * 20)) . '" width="12" height="12" fill="' . $color . '"/>';
            $legendContent .= '<text x="' . ($legendX + 20) . '" y="' . ($legendY + ($colorIndex * 20) + 10) . '" fill="#9CA3AF" font-size="12">' . htmlspecialchars($userName) . ' - ' . round($percentage, 1) . '% ($' . number_format($amount, 2) . ')</text>';
            $colorIndex++;
        }

        $svg = '<svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">';

        $centerX = 200;
        $centerY = 260;
        $radius = 120;

        if (count($userExpenses) === 1) {
            $userName = key($userExpenses);
            $amount = current($userExpenses);
            $color = $colors[0];
            $percentage = ($amount / $familyBudget) * 100;

            $svg .= '<circle cx="' . $centerX . '" cy="' . $centerY . '" r="' . $radius . '" fill="' . $color . '" stroke="white" stroke-width="2" />';
            $svg .= '<text x="' . $centerX . '" y="' . $centerY . '" text-anchor="middle" dominant-baseline="middle" fill="white" font-size="18" font-weight="bold">' . round($percentage, 1) . '%</text>';
        } else {
            $startAngle = 0;
            $colorIndex = 0;

            foreach ($userExpenses as $userName => $amount) {
                $percentage = ($amount / $familyBudget) * 100;
                $angle = ($amount / $familyBudget) * 360;

                $x1 = $centerX + $radius * cos(deg2rad($startAngle));
                $y1 = $centerY + $radius * sin(deg2rad($startAngle));
                $x2 = $centerX + $radius * cos(deg2rad($startAngle + $angle));
                $y2 = $centerY + $radius * sin(deg2rad($startAngle + $angle));

                $largeArc = $angle > 180 ? 1 : 0;

                $pathData = "M $centerX $centerY L $x1 $y1 A $radius $radius 0 $largeArc 1 $x2 $y2 Z";

                $svg .= '<path d="' . $pathData . '" fill="' . $colors[$colorIndex % count($colors)] . '" stroke="white" stroke-width="2"/>';

                $labelAngle = $startAngle + ($angle / 2);
                $labelX = $centerX + ($radius * 0.6) * cos(deg2rad($labelAngle));
                $labelY = $centerY + ($radius * 0.6) * sin(deg2rad($labelAngle));

                if ($percentage > 5) {
                    $svg .= '<text x="' . $labelX . '" y="' . $labelY . '" text-anchor="middle" dominant-baseline="middle" fill="white" font-size="12" font-weight="bold">' . round($percentage, 1) . '%</text>';
                }

                $startAngle += $angle;
                $colorIndex++;
            }
        }

        $svg .= $legendContent;
        $svg .= '</svg>';

        return $svg;
    }
}
