<?php

declare(strict_types=1);

namespace App\Diagrams\Generators;

use App\Dto\ProjectedSpendingResult;

final class ProjectedSpendingGenerator
{
    public function generateSvg(string $selectedMonth, ProjectedSpendingResult $r, ?float $budget): string
    {
        $vbW = 800;
        $vbH = 260;

        // Layout
        $margin = [
            'top' => 64,
            'right' => 24,
            'bottom' => 56,
            'left' => 64,
        ];
        $chartW = $vbW - $margin['left'] - $margin['right'];
        $chartH = $vbH - $margin['top'] - $margin['bottom'];

        // Colors
        $axisColor = '#334155';
        $gridColor = '#334155';
        $labelColor = '#E5E7EB';
        $actualColor = '#22C55E';
        $projectionColor = '#9CA3AF';
        $budgetColor = '#EF4444';
        $infoColor = '#94A3B8';

        // Scale Y
        $maxY = max(
            $r->projectedTotal,
            $budget ?? 0.0,
            $r->cumCurrent[$r->compareIndex] ?? 0.0
        );
        if ($maxY <= 0) {
            $maxY = 1.0;
        }
        $maxY *= 1.1; // headroom
        $scaleY = $chartH / $maxY;

        $mapX = static function (int $day) use ($r, $chartW, $margin): float {
            if ($r->daysInMonth <= 1) {
                return (float) $margin['left'];
            }
            $t = ($day - 1) / ($r->daysInMonth - 1);
            return $margin['left'] + $t * $chartW;
        };
        $mapY = static function (float $val) use ($chartH, $margin, $scaleY): float {
            return $margin['top'] + $chartH - ($val * $scaleY);
        };

        $svg = [];
        $svg[] = '<svg viewBox="0 0 ' . $vbW . ' ' . $vbH . '" width="100%" height="100%" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" role="img" style="display:block" aria-label="Projected current month spending for ' . htmlspecialchars($selectedMonth) . '">';

        // Info icon in top-left corner
        $svg[] = '<g>';
        $svg[] = '<circle cx="16" cy="16" r="8" fill="none" stroke="' . $infoColor . '" stroke-width="1.5"/>';
        $svg[] = '<text x="16" y="20" fill="' . $infoColor . '" font-size="12" font-weight="bold" text-anchor="middle">i</text>';
        $svg[] = '<title>Need at least 3 months for a prediction.</title>';
        $svg[] = '</g>';

        // Header
        $title = 'Spending Projection - ' . $selectedMonth;

        $subParts = [];
        $subParts[] = 'Spent to date: ' . number_format($r->currentToDate, 0);
        $subParts[] = 'Projected total: ' . number_format($r->projectedTotal, 0);
        if ($budget !== null) {
            $budgetInfo = 'Budget: ' . number_format($budget, 0);
            $budgetInfo .= ' | Budget hit: ' . ($r->budgetHit ? $r->budgetHit->format('M j') : 'n/a');
            $subParts[] = $budgetInfo;
        }
        $sub = implode(' | ', $subParts);

        $svg[] = '<text x="' . $margin['left'] . '" y="18" fill="' . $labelColor . '" font-size="18" font-weight="600">' . htmlspecialchars($title) . '</text>';
        $svg[] = '<text x="' . $margin['left'] . '" y="36" fill="' . $labelColor . '" font-size="12">' . htmlspecialchars($sub) . '</text>';

        // Growth colored line
        if ($r->prevToDate > 0.0) {
            $growthPct = ($r->currentToDate / $r->prevToDate - 1.0) * 100.0;
            $arrow = $growthPct >= 0 ? '▲' : '▼';
            $growthColor = $growthPct >= 0 ? $actualColor : $budgetColor;
            $growthFormatted = ($growthPct >= 0 ? '+' : '') . number_format($growthPct, 1) . '%';
            $svg[] = '<text x="' . $margin['left'] . '" y="52" fill="' . $labelColor . '" font-size="12">Growth vs last month to-date: <tspan fill="' . $growthColor . '" font-weight="600">' . $arrow . ' ' . $growthFormatted . '</tspan></text>';
        } else {
            $svg[] = '<text x="' . $margin['left'] . '" y="52" fill="' . $labelColor . '" font-size="12">Growth vs last month to-date: n/a</text>';
        }

        // Gridlines and Y ticks
        $gridLines = 4;
        for ($i = 0; $i <= $gridLines; $i++) {
            $val = $maxY * ($i / $gridLines);
            $y = $mapY($val);
            $svg[] = '<line x1="' . $margin['left'] . '" y1="' . $y . '" x2="' . ($vbW - $margin['right']) . '" y2="' . $y . '" stroke="' . $gridColor . '" stroke-width="1" />';
            $label = $val >= 1000 ? number_format($val / 1000, 1) . 'k' : number_format($val, 0);
            $svg[] = '<text x="' . ($margin['left'] - 10) . '" y="' . $y . '" fill="' . $labelColor . '" font-size="12" text-anchor="end" dominant-baseline="middle">' . $label . '</text>';
        }

        // Axes
        $xAxisY = $margin['top'] + $chartH;
        $svg[] = '<line x1="' . $margin['left'] . '" y1="' . $xAxisY . '" x2="' . ($vbW - $margin['right']) . '" y2="' . $xAxisY . '" stroke="' . $axisColor . '" stroke-width="1" />';
        $svg[] = '<line x1="' . $margin['left'] . '" y1="' . $margin['top'] . '" x2="' . $margin['left'] . '" y2="' . ($margin['top'] + $chartH) . '" stroke="' . $axisColor . '" stroke-width="1" />';

        // X ticks
        $tickEvery = max(1, (int) ceil($r->daysInMonth / 6));
        for ($d = 1; $d <= $r->daysInMonth; $d++) {
            if ($d % $tickEvery === 0 || $d === 1 || $d === $r->daysInMonth) {
                $x = $mapX($d);
                $svg[] = '<text x="' . $x . '" y="' . ($xAxisY + 16) . '" fill="' . $labelColor . '" font-size="12" text-anchor="middle">' . $d . '</text>';
            }
        }

        // Budget line
        if ($budget !== null) {
            $yBudget = $mapY($budget);
            $svg[] = '<line x1="' . $margin['left'] . '" y1="' . $yBudget . '" x2="' . ($vbW - $margin['right']) . '" y2="' . $yBudget . '" stroke="' . $budgetColor . '" stroke-width="2" stroke-dasharray="4,4" />';
            $svg[] = '<text x="' . ($vbW - $margin['right']) . '" y="' . ($yBudget - 6) . '" fill="' . $budgetColor . '" font-size="12" text-anchor="end">Family budget</text>';
        }

        // Actual path
        $pathActual = '';
        for ($d = 1; $d <= $r->compareIndex; $d++) {
            $x = $mapX($d);
            $y = $mapY($r->cumCurrent[$d] ?? 0.0);
            $pathActual .= ($d === 1 ? 'M' : 'L') . $x . ' ' . $y . ' ';
        }
        if ($pathActual !== '') {
            $svg[] = '<path d="' . trim($pathActual) . '" fill="none" stroke="' . $actualColor . '" stroke-width="3" />';
        }

        // Projection
        if ($r->compareIndex < $r->daysInMonth) {
            $x0 = $mapX($r->compareIndex);
            $y0 = $mapY($r->cumCurrent[$r->compareIndex] ?? 0.0);
            $x1 = $mapX($r->daysInMonth);
            $y1 = $mapY($r->projectedTotal);
            $svg[] = '<path d="M' . $x0 . ' ' . $y0 . ' L' . $x1 . ' ' . $y1 . '" fill="none" stroke="' . $projectionColor . '" stroke-width="3" stroke-dasharray="6,6" />';
        }

        // Markers
        $rDot = 3.5;
        $xToday = $mapX($r->compareIndex);
        $yToday = $mapY($r->cumCurrent[$r->compareIndex] ?? 0.0);
        $svg[] = '<circle cx="' . $xToday . '" cy="' . $yToday . '" r="' . $rDot . '" fill="' . $actualColor . '" />';
        $xEnd = $mapX($r->daysInMonth);
        $yEnd = $mapY($r->projectedTotal);
        $svg[] = '<circle cx="' . $xEnd . '" cy="' . $yEnd . '" r="' . $rDot . '" fill="' . $projectionColor . '" />';

        // Label near "today"
        $svg[] = '<text x="' . ($xToday + 6) . '" y="' . ($yToday - 8) . '" fill="' . $labelColor . '" font-size="12" dominant-baseline="ideographic">To date: ' . number_format($r->currentToDate, 0) . '</text>';

        // Legend (centered)
        $legendItems = [
            ['label' => 'Actual',    'color' => $actualColor],
            ['label' => 'Projected', 'color' => $projectionColor],
        ];
        if ($budget !== null) {
            $legendItems[] = ['label' => 'Budget', 'color' => $budgetColor];
        }

        $estimateTextWidth = static function (string $text): int {
            return (int) ceil(strlen($text) * 12 * 0.6);
        };
        $blockGap = 32;
        $swatchToText = 20;
        $swatchW = 14;
        $swatchH = 3;

        $blockWidths = [];
        $totalWidth = 0;
        foreach ($legendItems as $item) {
            $w = $swatchToText + $estimateTextWidth($item['label']);
            $blockWidths[] = $w;
            $totalWidth += $w;
        }
        $totalWidth += $blockGap * (max(0, count($legendItems) - 1));

        $centerX = $vbW / 2;
        $legendOffset = 40;
        $legendY = ($margin['top'] + $chartH) + $legendOffset;
        $startX = (int) round($centerX - ($totalWidth / 2));

        $cursorX = $startX;
        foreach ($legendItems as $index => $item) {
            $svg[] = '<rect x="' . $cursorX . '" y="' . ($legendY - 10) . '" width="' . $swatchW . '" height="' . $swatchH . '" fill="' . $item['color'] . '"/>';
            $textX = $cursorX + $swatchToText;
            $svg[] = '<text x="' . $textX . '" y="' . $legendY . '" fill="' . $labelColor . '" font-size="12">' . htmlspecialchars($item['label']) . '</text>';
            $cursorX += $blockWidths[$index] + $blockGap;
        }

        $svg[] = '</svg>';
        return implode('', $svg);
    }
}
