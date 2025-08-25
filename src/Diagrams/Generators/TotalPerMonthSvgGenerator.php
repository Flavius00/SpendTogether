<?php

declare(strict_types=1);

namespace App\Diagrams\Generators;

class TotalPerMonthSvgGenerator
{
    private const COLORS = ['#FF6384', '#36A2EB', '#FFCE56', '#4BC0C0', '#9966FF', '#FF9F40', '#34D399', '#F87171'];
    private const RED_SHADES = ['#DC2626', '#EF4444', '#F87171', '#FCA5A5', '#FECACA', '#FEE2E2', '#B91C1C', '#991B1B'];
    private const CENTER_X = 200;
    private const CENTER_Y = 260;
    private const RADIUS = 120;

    public function generateSvg(array $data, ?float $budget = null): string
    {
        if (empty($data)) {
            return $this->generateEmptyStateSvg('No expenses found');
        }

        $total = array_sum($data);
        $isOverBudget = ($budget !== null && $total > $budget);

        $legendData = $this->prepareLegendData($data, $total, $budget, $isOverBudget);

        return $this->generatePieChartSvg($data, $total, $budget, $legendData, $isOverBudget);
    }

    private function prepareLegendData(array $data, float $total, ?float $budget, bool $isOverBudget): array
    {
        $legendData = [];

        foreach ($data as $label => $amount) {
            if ($isOverBudget && $label === 'OVER BUDGET!') {
                $overBudgetAmount = $total - $budget;
                $legendData[] = [
                    'label' => 'OVER BUDGET! +$' . number_format($overBudgetAmount, 2),
                    'amount' => 0
                ];
            } else {
                $percentage = ($total > 0) ? ($amount / $total) * 100 : 0;
                $legendData[] = [
                    'label' => $label . ' - ' . round($percentage, 1) . '% ($' . number_format($amount, 2) . ')',
                    'amount' => $amount
                ];
            }
        }

        return $legendData;
    }

    private function generateEmptyStateSvg(): string
    {
        return '<svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">
            <text x="200" y="200" text-anchor="middle" dominant-baseline="middle" fill="#9CA3AF">'
            . htmlspecialchars("No data found") . '</text>
        </svg>';
    }

    private function generatePieChartSvg(array $data, float $total, ?float $budget, array $legendData, bool $isOverBudget = false): string
    {
        // la fel ca în Service
        $svg = '<svg viewBox="0 0 400 400" xmlns="http://www.w3.org/2000/svg">';
        $svg .= $this->generateLegend($legendData, $isOverBudget, $total, $budget);

        if (count($data) === 1) {
            $svg .= $this->generateSingleSlice($data, $total, $isOverBudget);
        } else {
            $svg .= $this->generateMultipleSlices($data, $total, $isOverBudget);
        }

        $svg .= '</svg>';
        return $svg;
    }

    private function generateLegend(array $legendData, bool $isOverBudget = false, float $total = 0, ?float $budget = 0): string
    {
        $colors = $isOverBudget ? self::RED_SHADES : self::COLORS;
        $legendContent = '';
        $legendX = 20;
        $legendY = 20;

        foreach ($legendData as $index => $item) {
            $color = $colors[$index % count($colors)];
            $y = $legendY + ($index * 20);

            $legendContent .= '<rect x="' . $legendX . '" y="' . $y . '" width="12" height="12" fill="' . $color . '"/>';
            $legendContent .= '<text x="' . ($legendX + 20) . '" y="' . ($y + 10) . '" fill="#9CA3AF" font-size="12">'
                . htmlspecialchars($item['label']) . '</text>';
        }

        if ($isOverBudget && $budget > 0) {
            $overAmount = $total - $budget;
            $legendContent .= '<text x="' . $legendX . '" y="' . ($legendY + count($legendData) * 20 + 20) . '" fill="#DC2626" font-size="14" font-weight="bold">'
                . 'OVER BUDGET! +$' . number_format($overAmount, 2) . '</text>';
        }

        return $legendContent;
    }

    private function generateSingleSlice(array $data, float $total, bool $isOverBudget = false): string
    {
        $percentage = (current($data) / $total) * 100;
        $colors = $isOverBudget ? self::RED_SHADES : self::COLORS;
        $color = $colors[0];

        return '<circle cx="' . self::CENTER_X . '" cy="' . self::CENTER_Y . '" r="' . self::RADIUS . '" fill="' . $color . '" stroke="white" stroke-width="2" />
            <text x="' . self::CENTER_X . '" y="' . self::CENTER_Y . '" text-anchor="middle" dominant-baseline="middle" fill="white" font-size="18" font-weight="bold">'
            . round($percentage, 1) . '%</text>';
    }

    private function generateMultipleSlices(array $data, float $total, bool $isOverBudget = false): string
    {
        $colors = $isOverBudget ? self::RED_SHADES : self::COLORS;
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

            $color = $colors[$colorIndex % count($colors)];
            $svg .= '<path d="' . $pathData . '" fill="' . $color . '" stroke="white" stroke-width="2"/>';

            if ($percentage > 5) {
                $labelAngle = $startAngle + ($angle / 2);
                $labelX = self::CENTER_X + (self::RADIUS * 0.6) * cos(deg2rad($labelAngle));
                $labelY = self::CENTER_Y + (self::RADIUS * 0.6) * sin(deg2rad($labelAngle));

                $svg .= '<text x="' . $labelX . '" y="' . $labelY . '" text-anchor="middle" dominant-baseline="middle" fill="white" font-size="12" font-weight="bold">'
                    . round($percentage, 1) . '%</text>';
            }

            $startAngle += $angle;
            $colorIndex++;
        }

        return $svg;
    }
}
