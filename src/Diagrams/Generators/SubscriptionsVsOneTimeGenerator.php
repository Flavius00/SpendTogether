<?php

declare(strict_types=1);

namespace App\Diagrams\Generators;

use App\Dto\SubscriptionsVsOneTimeResult;

final class SubscriptionsVsOneTimeGenerator
{
    public function generateSvg(SubscriptionsVsOneTimeResult $data): string
    {
        // If no data in the window, show the empty state (as before)
        if ($data->maxVal <= 0.0) {
            return $this->noDataSvg("No data found for the last {$data->months} months");
        }

        // 5) Responsive SVG
        $vbWidth = 800;
        $vbHeight = 260;

        // Colors for dark theme
        $colorSubs = '#36A2EB';
        $colorOne  = '#FF9F40';
        $axisColor = '#334155';
        $labelColor = '#E5E7EB';

        // Fonts
        $fontTick = 20;
        $fontLabel = 20;
        $fontLegend = 20;

        // Legend band
        $legendSize = 16;
        $legendGap  = 12;
        $legendVPadTop = 8;
        $legendVPadBottom = 8;
        $legendBandHeight = max($legendSize, $fontLegend) + $legendVPadTop + $legendVPadBottom;

        $approx = static function (string $text, int $fontSize): float {
            return strlen($text) * ($fontSize * 0.6);
        };
        $label1 = 'Subscriptions';
        $label2 = 'One-time';

        $w1 = $legendSize + $legendGap + $approx($label1, $fontLegend);
        $w2 = $legendSize + $legendGap + $approx($label2, $fontLegend);
        $legendGroupGap = 32;
        $legendTotalWidth = $w1 + $legendGroupGap + $w2;

        // Margins include the legend band
        $margin = [
            'top' => 16 + $legendBandHeight,
            'right' => 18,
            'bottom' => 66,
            'left' => 56,
        ];
        $chartW = $vbWidth - $margin['left'] - $margin['right'];
        $chartH = $vbHeight - $margin['top'] - $margin['bottom'];

        // Bars geometry
        $groupCount = count($data->monthsKeys);
        $groupStride = $groupCount > 0 ? ($chartW / $groupCount) : $chartW;
        $groupPadRatio = 0.18;
        $groupPad = $groupStride * $groupPadRatio;
        $barWidth = max(6.0, ($groupStride - $groupPad) / 2.0);

        $svg = [];
        $svg[] = '<svg viewBox="0 0 ' . $vbWidth . ' ' . $vbHeight . '" width="100%" height="100%" preserveAspectRatio="xMidYMid meet" xmlns="http://www.w3.org/2000/svg" role="img" style="display:block" aria-label="Subscriptions vs One-time expenses, last ' . $data->months . ' months">';

        // Legend – centered in the top band
        $legendX = max(8.0, ($vbWidth - $legendTotalWidth) / 2.0);
        $legendYMid = $legendVPadTop + max($legendSize, $fontLegend) / 2.0;

        $x1Rect = $legendX;
        $x1Text = $x1Rect + $legendSize + $legendGap;
        $x2Rect = $x1Text + $approx($label1, $fontLegend) + $legendGroupGap;
        $x2Text = $x2Rect + $legendSize + $legendGap;

        $svg[] = '<g>';
        $svg[] = '<rect x="' . $x1Rect . '" y="' . ($legendYMid - $legendSize / 2) . '" width="' . $legendSize . '" height="' . $legendSize . '" fill="' . $colorSubs . '" rx="2" />';
        $svg[] = '<text x="' . $x1Text . '" y="' . $legendYMid . '" fill="' . $labelColor . '" font-size="' . $fontLegend . '" dominant-baseline="middle">' . $label1 . '</text>';
        $svg[] = '<rect x="' . $x2Rect . '" y="' . ($legendYMid - $legendSize / 2) . '" width="' . $legendSize . '" height="' . $legendSize . '" fill="' . $colorOne . '" rx="2" />';
        $svg[] = '<text x="' . $x2Text . '" y="' . $legendYMid . '" fill="' . $labelColor . '" font-size="' . $fontLegend . '" dominant-baseline="middle">' . $label2 . '</text>';
        $svg[] = '</g>';

        // GRID + Axes
        $gridLines = 4;
        for ($i = 0; $i <= $gridLines; $i++) {
            $y = $margin['top'] + $chartH - ($chartH * ($i / $gridLines));
            $val = ($data->maxVal * ($i / $gridLines));
            $svg[] = '<line x1="' . $margin['left'] . '" y1="' . $y . '" x2="' . ($vbWidth - $margin['right']) . '" y2="' . $y . '" stroke="' . $axisColor . '" stroke-width="1" />';
            $svg[] = '<text x="' . ($margin['left'] - 10) . '" y="' . $y . '" fill="' . $labelColor . '" font-size="' . $fontTick . '" text-anchor="end" dominant-baseline="middle">' . $this->formatAmountTick($val) . '</text>';
        }

        // X axis
        $xAxisY = $vbHeight - $margin['bottom'];
        $svg[] = '<line x1="' . $margin['left'] . '" y1="' . $xAxisY . '" x2="' . ($vbWidth - $margin['right']) . '" y2="' . $xAxisY . '" stroke="' . $axisColor . '" stroke-width="1" />';

        // Bars
        $scale = $chartH / $data->maxVal;
        foreach (array_values($data->monthsKeys) as $index => $key) {
            $groupStartX = $margin['left'] + $index * $groupStride;
            $bar1X = $groupStartX + ($groupPad / 2);
            $bar2X = $bar1X + $barWidth;

            $subsVal = $data->subsTotals[$key] ?? 0.0;
            $oneVal  = $data->oneTimeTotals[$key] ?? 0.0;

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

            // Month label – month name only
            $label = htmlspecialchars($data->labels[$key] ?? $key);
            $labelX = $groupStartX + $groupStride / 2;
            $labelY = $xAxisY + 8;
            $svg[] = '<text x="' . $labelX . '" y="' . $labelY . '" fill="' . $labelColor . '" font-size="' . $fontLabel . '" text-anchor="middle" dominant-baseline="hanging">' . $label . '</text>';
        }

        $svg[] = '</svg>';

        return implode('', $svg);
    }

    public function noDataSvg(string $message): string
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
