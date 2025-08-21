<?php

declare(strict_types=1);

namespace App\Controller\Service;

use App\Entity\Expense;
use App\Entity\Family;
use App\Entity\User;

final class SubscriptionsVsOneTimeSvgService
{
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

    private function generateUserSvgMonths(User $user, int $months): string
    {
        $expenses = $user->getExpenses();
        return $this->buildChartSvgFromExpensesIterator([$expenses], $months);
    }

    public function generateFamilySvg(Family $family): string
    {
        return $this->generateFamilySvgMonths($family, 12);
    }

    private function generateFamilySvgMonths(Family $family, int $months): string
    {
        $users = $family->getUsers();
        if (!$users || count($users) === 0) {
            return $this->noDataSvg('No family members');
        }

        $iterators = [];
        foreach ($users as $u) {
            $iterators[] = $u->getExpenses();
        }

        return $this->buildChartSvgFromExpensesIterator($iterators, $months);
    }

    /**
     * @param iterable[] $expensesIterators list of Doctrine Collections/iterables of Expense
     */
    private function buildChartSvgFromExpensesIterator(array $expensesIterators, int $months): string
    {
        // 1) Months window (including current month) and labels with month name only
        $monthsKeys = [];
        $labels = [];
        $now = new \DateTime('first day of this month');
        for ($i = $months - 1; $i >= 0; $i--) {
            $m = (clone $now)->modify("-$i months");
            $key = $m->format('Y-m');
            $monthsKeys[] = $key;
            $labels[$key] = $m->format('M'); // Month name only
        }

        // 2) Totals
        $subsTotals = array_fill_keys($monthsKeys, 0.0);
        $oneTimeTotals = array_fill_keys($monthsKeys, 0.0);

        // 3) Aggregation
        $firstMonth = (clone $now)->modify('-' . ($months - 1) . ' months');
        $startBoundary = (clone $firstMonth)->setTime(0, 0, 0);
        $endBoundary = (clone $now)->modify('last day of this month')->setTime(23, 59, 59);

        foreach ($expensesIterators as $collection) {
            foreach ($collection as $expense) {
                if (!$expense instanceof Expense) {
                    continue;
                }
                $date = $expense->getDate();
                if (!$date || $date < $startBoundary || $date > $endBoundary) {
                    continue;
                }

                $key = $date->format('Y-m');
                if (!isset($subsTotals[$key])) {
                    continue;
                }

                $amount = (float) $expense->getAmount();
                if ($expense->getSubscription() !== null) {
                    $subsTotals[$key] += $amount;
                } else {
                    $oneTimeTotals[$key] += $amount;
                }
            }
        }

        // 4) Scale
        $maxSubs = empty($subsTotals) ? 0.0 : max($subsTotals);
        $maxOne = empty($oneTimeTotals) ? 0.0 : max($oneTimeTotals);
        $maxVal = max($maxSubs, $maxOne);

        if ($maxVal <= 0.0) {
            return $this->noDataSvg("No data found for the last $months months");
        }

        // 5) Responsive SVG
        $vbWidth = 800;
        $vbHeight = 260;

        // Colors for dark theme
        $colorSubs = '#36A2EB';
        $colorOne  = '#FF9F40';
        $axisColor = '#334155';
        $labelColor = '#E5E7EB';

        // Fonts (as requested)
        $fontTick = 20;
        $fontLabel = 20;
        $fontLegend = 20;

        // Legend: properly sized legend band and centered
        $legendSize = 16;    // colored square size
        $legendGap  = 12;    // gap between square and text
        $legendVPadTop = 8;  // top padding in legend band
        $legendVPadBottom = 8; // bottom padding in legend band
        $legendBandHeight = max($legendSize, $fontLegend) + $legendVPadTop + $legendVPadBottom;

        // Approximate text width for centering (0.6em/char is a reasonable estimate)
        $approx = static function (string $text, int $fontSize): float {
            return strlen($text) * ($fontSize * 0.6);
        };
        $label1 = 'Subscriptions';
        $label2 = 'One-time';

        $w1 = $legendSize + $legendGap + $approx($label1, $fontLegend);
        $w2 = $legendSize + $legendGap + $approx($label2, $fontLegend);
        $legendGroupGap = 32; // space between the two legend items
        $legendTotalWidth = $w1 + $legendGroupGap + $w2;

        // Margins: include the legend band at the top so it doesn't overlap the chart
        $margin = [
            'top' => 16 + $legendBandHeight, // 16px spacing before the chart under the legend
            'right' => 18,
            'bottom' => 66,
            'left' => 56,
        ];
        $chartW = $vbWidth - $margin['left'] - $margin['right'];
        $chartH = $vbHeight - $margin['top'] - $margin['bottom'];

        // Bars geometry
        $groupCount = count($monthsKeys);
        $groupStride = $chartW / $groupCount;
        $groupPadRatio = 0.18;
        $groupPad = $groupStride * $groupPadRatio;
        $barWidth = max(6.0, ($groupStride - $groupPad) / 2.0);

        $svg = [];
        $svg[] = '<svg viewBox="0 0 ' . $vbWidth . ' ' . $vbHeight . '" width="100%" height="100%" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" role="img" style="display:block" aria-label="Subscriptions vs One-time expenses, last ' . $months . ' months">';

        // LEGEND – placed in the top band, horizontally centered
        $legendX = max(8.0, ($vbWidth - $legendTotalWidth) / 2.0);
        $legendYMid = $legendVPadTop + max($legendSize, $fontLegend) / 2.0;

        // Item 1
        $x1Rect = $legendX;
        $x1Text = $x1Rect + $legendSize + $legendGap;

        // Item 2
        $x2Rect = $x1Text + $approx($label1, $fontLegend) + $legendGroupGap;
        $x2Text = $x2Rect + $legendSize + $legendGap;

        $svg[] = '<g>';
        // colored squares aligned to the middle of the band
        $svg[] = '<rect x="' . $x1Rect . '" y="' . ($legendYMid - $legendSize / 2) . '" width="' . $legendSize . '" height="' . $legendSize . '" fill="' . $colorSubs . '" rx="2" />';
        $svg[] = '<text x="' . $x1Text . '" y="' . $legendYMid . '" fill="' . $labelColor . '" font-size="' . $fontLegend . '" dominant-baseline="middle">' . $label1 . '</text>';
        $svg[] = '<rect x="' . $x2Rect . '" y="' . ($legendYMid - $legendSize / 2) . '" width="' . $legendSize . '" height="' . $legendSize . '" fill="' . $colorOne . '" rx="2" />';
        $svg[] = '<text x="' . $x2Text . '" y="' . $legendYMid . '" fill="' . $labelColor . '" font-size="' . $fontLegend . '" dominant-baseline="middle">' . $label2 . '</text>';
        $svg[] = '</g>';

        // GRID + Axes
        $gridLines = 4;
        for ($i = 0; $i <= $gridLines; $i++) {
            $y = $margin['top'] + $chartH - ($chartH * ($i / $gridLines));
            $val = ($maxVal * ($i / $gridLines));
            $svg[] = '<line x1="' . $margin['left'] . '" y1="' . $y . '" x2="' . ($vbWidth - $margin['right']) . '" y2="' . $y . '" stroke="' . $axisColor . '" stroke-width="1" />';
            $svg[] = '<text x="' . ($margin['left'] - 10) . '" y="' . $y . '" fill="' . $labelColor . '" font-size="' . $fontTick . '" text-anchor="end" dominant-baseline="middle">' . $this->formatAmountTick($val) . '</text>';
        }

        // X axis
        $xAxisY = $vbHeight - $margin['bottom'];
        $svg[] = '<line x1="' . $margin['left'] . '" y1="' . $xAxisY . '" x2="' . ($vbWidth - $margin['right']) . '" y2="' . $xAxisY . '" stroke="' . $axisColor . '" stroke-width="1" />';

        // Bars
        $scale = $chartH / $maxVal;
        foreach (array_values($monthsKeys) as $index => $key) {
            $groupStartX = $margin['left'] + $index * $groupStride;
            $bar1X = $groupStartX + ($groupPad / 2);
            $bar2X = $bar1X + $barWidth;

            $subsVal = $subsTotals[$key] ?? 0.0;
            $oneVal  = $oneTimeTotals[$key] ?? 0.0;

            $subsH = $subsVal * $scale;
            $oneH  = $oneVal * $scale;

            $subsY = $margin['top'] + $chartH - $subsH;
            $oneY  = $margin['top'] + $chartH - $oneH;

            if ($subsVal > 0) {
                $svg[] = '<rect x="' . $bar1X . '" y="' . $subsY . '" width="' . $barWidth . '" height="' . $subsH . '" fill="' . $colorSubs . '" rx="2" />';
            } else {
                $svg[] = '<rect x="' . $bar1X . '" y="' . ($margin['top'] + $chartH - 1) . '" width="' . $barWidth . '" height="1" fill="' . $axisColor . '" />';
            }

            if ($oneVal > 0) {
                $svg[] = '<rect x="' . $bar2X . '" y="' . $oneY . '" width="' . $barWidth . '" height="' . $oneH . '" fill="' . $colorOne . '" rx="2" />';
            } else {
                $svg[] = '<rect x="' . $bar2X . '" y="' . ($margin['top'] + $chartH - 1) . '" width="' . $barWidth . '" height="1" fill="' . $axisColor . '" />';
            }

            // Month label – month name only, vertical space below the axis
            $label = htmlspecialchars($labels[$key] ?? $key);
            $labelX = $groupStartX + $groupStride / 2;
            $labelY = $xAxisY + 8;
            $svg[] = '<text x="' . $labelX . '" y="' . $labelY . '" fill="' . $labelColor . '" font-size="' . $fontLabel . '" text-anchor="middle" dominant-baseline="hanging">' . $label . '</text>';
        }

        $svg[] = '</svg>';

        return implode('', $svg);
    }

    private function noDataSvg(string $message): string
    {
        $msg = htmlspecialchars($message);
        return '<svg viewBox="0 0 400 180" width="100%" height="100%" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" style="display:block">
            <text x="200" y="90" text-anchor="middle" dominant-baseline="middle" fill="#CBD5E1" font-size="16">' . $msg . '</text>
        </svg>';
    }

    private function formatAmountTick(float $v): string
    {
        if ($v >= 1000) {
            return number_format($v / 1000, 1) . 'k';
        }
        return (string) (intval($v) === $v ? intval($v) : number_format($v, 0));
    }
}
