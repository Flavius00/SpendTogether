<?php

namespace App\Controller\Service;

use App\Entity\Family;
use App\Entity\User;

class SelectedMonthVsLastMonthSvgService
{
    private const CHART_WIDTH = 800;
    private const CHART_HEIGHT = 500; // Increased for legend space
    private const MARGIN_LEFT = 60;
    private const MARGIN_RIGHT = 40;
    private const MARGIN_TOP = 40;
    private const MARGIN_BOTTOM = 100; // Increased for legend
    private const Y_INCREMENT = 500;

    public function generateSvg(string $option, string $selectedDate, User $user): string
    {
        if (empty($selectedDate)) {
            $selectedDate = (new \DateTime())->format('Y-m');
        }

        if ($option === 'user') {
            return $this->generateUserSvg($selectedDate, $user);
        } else {
            return $this->generateFamilySvg($selectedDate, $user->getFamily());
        }
    }

    private function generateUserSvg(string $selectedDate, User $user): string
    {
        $selectedMonthData = $this->calculateUserDailyExpenses($user, $selectedDate);
        $previousMonthData = $this->calculateUserDailyExpenses($user, $this->getPreviousMonth($selectedDate));

        return $this->generateChart($selectedMonthData, $previousMonthData, $selectedDate);
    }

    private function generateFamilySvg(string $selectedDate, Family $family): string
    {
        $selectedMonthData = $this->calculateFamilyDailyExpenses($family, $selectedDate);
        $previousMonthData = $this->calculateFamilyDailyExpenses($family, $this->getPreviousMonth($selectedDate));

        return $this->generateChart($selectedMonthData, $previousMonthData, $selectedDate);
    }

    private function calculateUserDailyExpenses(User $user, string $month): array
    {
        $expenses = $user->getExpenses();
        $dailyTotals = [];
        $daysInMonth = $this->getDaysInMonth($month);
        $isCurrentMonth = $this->isCurrentMonth($month);
        $today = (int)(new \DateTime())->format('d');

        // For current month, only go up to today's date
        $maxDay = $isCurrentMonth ? $today : $daysInMonth;

        // Initialize all days with 0
        for ($day = 1; $day <= $maxDay; $day++) {
            $dailyTotals[$day] = 0;
        }

        foreach ($expenses as $expense) {
            if ($expense->getDate()->format('Y-m') === $month) {
                $day = (int)$expense->getDate()->format('d');
                if ($day <= $maxDay) {
                    $dailyTotals[$day] += $expense->getAmount();
                }
            }
        }

        // Convert to cumulative totals
        return $this->convertToCumulative($dailyTotals);
    }

    private function calculateFamilyDailyExpenses(Family $family, string $month): array
    {
        $dailyTotals = [];
        $daysInMonth = $this->getDaysInMonth($month);
        $isCurrentMonth = $this->isCurrentMonth($month);
        $today = (int)(new \DateTime())->format('d');

        // For current month, only go up to today's date
        $maxDay = $isCurrentMonth ? $today : $daysInMonth;

        // Initialize all days with 0
        for ($day = 1; $day <= $maxDay; $day++) {
            $dailyTotals[$day] = 0;
        }

        foreach ($family->getUsers() as $user) {
            foreach ($user->getExpenses() as $expense) {
                if ($expense->getDate()->format('Y-m') === $month) {
                    $day = (int)$expense->getDate()->format('d');
                    if ($day <= $maxDay) {
                        $dailyTotals[$day] += $expense->getAmount();
                    }
                }
            }
        }

        // Convert to cumulative totals
        return $this->convertToCumulative($dailyTotals);
    }

    private function convertToCumulative(array $dailyTotals): array
    {
        $cumulative = [];
        $runningTotal = 0;

        foreach ($dailyTotals as $day => $amount) {
            $runningTotal += $amount;
            $cumulative[$day] = $runningTotal;
        }

        return $cumulative;
    }

    private function generateChart(array $selectedMonthData, array $previousMonthData, string $selectedDate): string
    {
        $maxAmount = max(max($selectedMonthData), max($previousMonthData));
        $maxY = ceil($maxAmount / self::Y_INCREMENT) * self::Y_INCREMENT;
        if ($maxY === 0) $maxY = self::Y_INCREMENT;

        $fullMonthDays = $this->getDaysInMonth($selectedDate);
        $chartWidth = self::CHART_WIDTH - self::MARGIN_LEFT - self::MARGIN_RIGHT;
        $chartHeight = self::CHART_HEIGHT - self::MARGIN_TOP - self::MARGIN_BOTTOM;

        $svg = '<svg viewBox="0 0 ' . self::CHART_WIDTH . ' ' . self::CHART_HEIGHT . '" xmlns="http://www.w3.org/2000/svg">';

        // Chart background
        $svg .= '<rect x="' . self::MARGIN_LEFT . '" y="' . self::MARGIN_TOP . '" width="' . $chartWidth . '" height="' . $chartHeight . '" fill="none" stroke="#E5E7EB" stroke-width="1"/>';

        // Grid lines and Y-axis labels
        $svg .= $this->generateYAxisAndGrid($maxY, $chartHeight);

        // X-axis labels
        $svg .= $this->generateXAxis($fullMonthDays, $chartWidth);

        // Chart lines
        $svg .= $this->generateLine($selectedMonthData, $maxY, $chartWidth, $chartHeight, '#10B981', 'Selected Month', $fullMonthDays);
        $svg .= $this->generateLine($previousMonthData, $maxY, $chartWidth, $chartHeight, '#EF4444', 'Previous Month', $fullMonthDays);

        // Legend below chart
        $svg .= $this->generateLegend($selectedDate, $chartHeight);

        $svg .= '</svg>';

        return $svg;
    }

    private function generateYAxisAndGrid(float $maxY, int $chartHeight): string
    {
        $svg = '';
        $steps = (int)($maxY / self::Y_INCREMENT) ?: 1;

        for ($i = 0; $i <= $steps; $i++) {
            $amount = $i * self::Y_INCREMENT;
            $y = self::MARGIN_TOP + $chartHeight - ($i / $steps) * $chartHeight;

            // Grid line
            $svg .= '<line x1="' . self::MARGIN_LEFT . '" y1="' . $y . '" x2="' . (self::CHART_WIDTH - self::MARGIN_RIGHT) . '" y2="' . $y . '" stroke="#F3F4F6" stroke-width="1"/>';

            // Y-axis label
            $svg .= '<text x="' . (self::MARGIN_LEFT - 10) . '" y="' . ($y + 5) . '" text-anchor="end" fill="#6B7280" font-size="12">$' . number_format($amount) . '</text>';
        }

        return $svg;
    }

    private function generateXAxis(int $daysInMonth, int $chartWidth): string
    {
        $svg = '';
        $stepSize = $chartWidth / $daysInMonth;

        for ($day = 1; $day <= $daysInMonth; $day++) {
            $x = self::MARGIN_LEFT + ($day - 0.5) * $stepSize;

            // Show every 5th day to avoid crowding
            if ($day % 5 === 1 || $day === $daysInMonth) {
                $svg .= '<text x="' . $x . '" y="' . (self::CHART_HEIGHT - self::MARGIN_BOTTOM + 20) . '" text-anchor="middle" fill="#6B7280" font-size="12">' . $day . '</text>';
            }
        }

        return $svg;
    }

    private function generateLine(array $data, float $maxY, int $chartWidth, int $chartHeight, string $color, string $label, int $fullMonthDays): string
    {
        if (empty($data)) return '';

        $svg = '';
        $points = [];
        $stepSize = $chartWidth / $fullMonthDays;
        $maxY = $maxY ?: 1;

        foreach ($data as $day => $amount) {
            $x = self::MARGIN_LEFT + ($day - 0.5) * $stepSize;
            $y = self::MARGIN_TOP + $chartHeight - ($amount / $maxY) * $chartHeight;
            $points[] = $x . ',' . $y;
        }

        // Draw line
        $svg .= '<polyline points="' . implode(' ', $points) . '" fill="none" stroke="' . $color . '" stroke-width="2"/>';

        // Draw points
        $lastDay = 0;
        $lastAmount = 0;
        $lastX = 0;
        $lastY = 0;

        foreach ($data as $day => $amount) {
            $x = self::MARGIN_LEFT + ($day - 0.5) * $stepSize;
            $y = self::MARGIN_TOP + $chartHeight - ($amount / $maxY) * $chartHeight;
            $svg .= '<circle cx="' . $x . '" cy="' . $y . '" r="3" fill="' . $color . '"/>';

            // Keep track of last point
            if ($day > $lastDay) {
                $lastDay = $day;
                $lastAmount = $amount;
                $lastX = $x;
                $lastY = $y;
            }
        }

        // Add total amount text below the last point
        if ($lastDay > 0) {
            $svg .= '<text x="' . $lastX . '" y="' . ($lastY + 20) . '" fill="' . $color . '" font-size="14" font-weight="bold" text-anchor="middle">$' . number_format($lastAmount, 0) . '</text>';
        }

        return $svg;
    }

    private function generateLegend(string $selectedDate, int $chartHeight): string
    {
        $selectedMonthName = (new \DateTime($selectedDate))->format('F Y');
        $previousMonthName = (new \DateTime($this->getPreviousMonth($selectedDate)))->format('F Y');

        $legendY = self::MARGIN_TOP + $chartHeight + 40;
        $centerX = self::CHART_WIDTH / 2;

        $svg = '<g>';

        // Selected month legend (centered)
        $svg .= '<line x1="' . ($centerX - 150) . '" y1="' . $legendY . '" x2="' . ($centerX - 130) . '" y2="' . $legendY . '" stroke="#10B981" stroke-width="5"/>';
        $svg .= '<text x="' . ($centerX - 125) . '" y="' . ($legendY + 5) . '" fill="white" font-size="18">' . htmlspecialchars($selectedMonthName) . '</text>';

        // Previous month legend (centered)
        $svg .= '<line x1="' . ($centerX + 30) . '" y1="' . $legendY . '" x2="' . ($centerX + 50) . '" y2="' . $legendY . '" stroke="#EF4444" stroke-width="5"/>';
        $svg .= '<text x="' . ($centerX + 55) . '" y="' . ($legendY + 5) . '" fill="white" font-size="18">' . htmlspecialchars($previousMonthName) . '</text>';

        $svg .= '</g>';

        return $svg;
    }

    private function getPreviousMonth(string $month): string
    {
        $date = new \DateTime($month . '-01');
        $date->modify('-1 month');
        return $date->format('Y-m');
    }

    private function getDaysInMonth(string $month): int
    {
        $date = new \DateTime($month . '-01');
        return (int)$date->format('t');
    }

    private function isCurrentMonth(string $month): bool
    {
        $currentMonth = (new \DateTime())->format('Y-m');
        return $month === $currentMonth;
    }
}
