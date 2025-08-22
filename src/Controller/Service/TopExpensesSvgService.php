<?php

declare(strict_types=1);

namespace App\Controller\Service;

use App\Entity\Expense;
use App\Entity\Family;
use App\Entity\User;

final class TopExpensesSvgService
{
    /**
     * @param string      $option         'user' | 'family'
     * @param User        $user
     * @param string|null $selectedMonth  Month key 'YYYY-MM' (defaults to current month)
     */
    public function generateSvg(string $option, User $user, ?string $selectedMonth = null): string
    {
        [$monthRef, $label] = $this->resolveSelectedMonth($selectedMonth);

        if ($option === 'family' && $user->getFamily()) {
            return $this->generateFamilyTopSvg($user->getFamily(), $monthRef, $label);
        }

        return $this->generateUserTopSvg($user, $monthRef, $label);
    }

    private function generateUserTopSvg(User $user, \DateTime $monthRef, string $label): string
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
            $amount = (float) $expense->getAmount();
            $rows[] = [
                'amount' => $amount,
                'name' => (string) $expense->getName(),
                'user' => null,
            ];
        }

        return $this->buildTopSvg($rows, $label, showUser: false);
    }

    private function generateFamilyTopSvg(Family $family, \DateTime $monthRef, string $label): string
    {
        $rows = [];

        $users = $family->getUsers();
        if (!$users || count($users) === 0) {
            return $this->noDataSvg('No family members');
        }

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
                $amount = (float) $expense->getAmount();
                $rows[] = [
                    'amount' => $amount,
                    'name' => (string) $expense->getName(),
                    'user' => $displayUser,
                ];
            }
        }

        return $this->buildTopSvg($rows, $label, showUser: true);
    }

    private function buildTopSvg(array $rows, string $periodLabel, bool $showUser): string
    {
        // Sort by amount desc and keep top 5
        usort($rows, static fn ($a, $b) => $b['amount'] <=> $a['amount']);
        $top = array_slice($rows, 0, 5);

        // ViewBox width (height is computed); scales responsively in the container
        $width = 600;

        // Colors
        $textColor   = '#E5E7EB';
        $mutedColor  = '#94A3B8';
        $accentColor = '#22C55E';

        // FONTS
        $titleSize = 30;
        $headerSize = 24;
        $rowSize = 28;
        $valueSize = 28;

        // Layout and spacing
        $padTop = 28;
        $padBottom = 28;
        $padSide = 28;
        $titleGap = 22;
        $headerGap = 22;
        $rowGap = 40;
        $rowHeight = 28;
        $badgeSize = 30;

        // Space reserved for the right-aligned amount (+ a small gap)
        $reserveRight = 140; // px
        $gapToAmount = 8;    // px

        $rowsCount = max(1, count($top));
        $height = $padTop + $titleSize + $titleGap + $headerSize + $headerGap
            + ($rowsCount * $rowHeight) + (($rowsCount - 1) * $rowGap) + $padBottom;

        $svg = [];
        $svg[] = '<svg viewBox="0 0 ' . $width . ' ' . $height . '" width="100%" height="100%" preserveAspectRatio="xMinYMin meet" xmlns="http://www.w3.org/2000/svg" role="img" style="display:block" aria-label="Top 5 expenses for ' . htmlspecialchars($periodLabel) . '">';

        // Title
        $title = 'Top 5 - ' . $periodLabel;
        $titleX = $padSide;
        $titleY = $padTop + $titleSize;
        $svg[] = '<text x="' . $titleX . '" y="' . $titleY . '" fill="' . $textColor . '" font-size="' . $titleSize . '" font-weight="600">' . htmlspecialchars($title) . '</text>';

        // Empty state
        if (count($top) === 0) {
            $svg[] = '<text x="' . ($width / 2) . '" y="' . ($height / 2) . '" fill="' . $mutedColor . '" font-size="14" text-anchor="middle" dominant-baseline="middle">No expenses found</text>';
            $svg[] = '</svg>';
            return implode('', $svg);
        }

        // Header (no category)
        $headerY = $titleY + $titleGap + $headerSize;
        $headerLabel = $showUser ? 'Name / User' : 'Name';
        $svg[] = '<text x="' . $padSide . '" y="' . $headerY . '" fill="' . $mutedColor . '" font-size="' . $headerSize . '">' . $headerLabel . '</text>';
        $svg[] = '<text x="' . ($width - $padSide) . '" y="' . $headerY . '" fill="' . $mutedColor . '" font-size="' . $headerSize . '" text-anchor="end">Amount</text>';

        // Rows
        $startY = $headerY + $headerGap;
        foreach ($top as $i => $r) {
            $y = $startY + $i * ($rowHeight + $rowGap);

            // Rank badge (position)
            $badgeX = $padSide;
            $badgeY = $y + $rowHeight / 2 - $badgeSize / 2;
            $svg[] = '<rect x="' . $badgeX . '" y="' . $badgeY . '" width="' . $badgeSize . '" height="' . $badgeSize . '" rx="4" fill="#334155"/>';
            $svg[] = '<text x="' . ($badgeX + $badgeSize / 2) . '" y="' . ($badgeY + $badgeSize / 2) . '" fill="' . $textColor . '" font-size="12" text-anchor="middle" dominant-baseline="middle">' . ($i + 1) . '</text>';

            // Details (no category) with width-based truncation
            $textX = $badgeX + $badgeSize + 10;
            $textY = $y + $rowHeight / 2;

            $maxDetailsWidth = ($width - $padSide - $reserveRight - $gapToAmount) - $textX;

            $name = (string)($r['name'] ?? '');
            $details = '';
            if ($showUser) {
                $userDisp = (string)($r['user'] ?? '');
                // Split available width between name and user (approx. 65% / 35%)
                $nameMax = $maxDetailsWidth * 0.65;
                $userMax = $maxDetailsWidth * 0.35;

                $nameT = $this->truncateToWidth($name, $rowSize, $nameMax);
                $userT = $this->truncateToWidth($userDisp, $rowSize, $userMax);
                $details = htmlspecialchars($nameT) . ' • ' . htmlspecialchars($userT);
            } else {
                $nameT = $this->truncateToWidth($name, $rowSize, $maxDetailsWidth);
                $details = htmlspecialchars($nameT);
            }

            $svg[] = '<text x="' . $textX . '" y="' . $textY . '" fill="' . $textColor . '" font-size="' . $rowSize . '" dominant-baseline="middle">' . $details . '</text>';

            // Amount on the right
            $valX = $width - $padSide;
            $amountStr = $this->formatAmount($r['amount'] ?? 0.0);
            $svg[] = '<text x="' . $valX . '" y="' . $textY . '" fill="' . $accentColor . '" font-size="' . $valueSize . '" text-anchor="end" dominant-baseline="middle">' . $amountStr . '</text>';
        }

        $svg[] = '</svg>';
        return implode('', $svg);
    }

    /**
     * Normalize selected month (YYYY-MM) to a DateTime (first day of month) and a label.
     *
     * @return array{0:\DateTime,1:string}
     */
    private function resolveSelectedMonth(?string $selectedMonth): array
    {
        if ($selectedMonth && preg_match('/^\d{4}-\d{2}$/', $selectedMonth) === 1) {
            $firstDay = \DateTime::createFromFormat('Y-m-d H:i:s', $selectedMonth . '-01 00:00:00') ?: new \DateTime('first day of this month');
        } else {
            $firstDay = new \DateTime('first day of this month');
        }
        $label = $firstDay->format('M');
        return [$firstDay, $label];
    }

    private function formatAmount(float $amount): string
    {
        return number_format($amount, 0, '.', ',');
    }

    private function truncate(string $s, int $max): string
    {
        $s = trim($s);
        if (mb_strlen($s) <= $max) {
            return $s;
        }
        return rtrim(mb_substr($s, 0, $max - 1)) . '…';
    }

    /**
     * Truncate text based on available width (approx. 0.6em per character).
     */
    private function truncateToWidth(string $text, int $fontSize, float $maxWidth): string
    {
        $text = trim($text);
        if ($text === '') {
            return $text;
        }
        $factor = 0.6; // approximation: 0.6 * fontSize per character
        $maxChars = (int) floor($maxWidth / ($fontSize * $factor));
        if ($maxChars <= 0) {
            return '…';
        }
        // keep one character for the ellipsis when truncating
        $limit = max(1, $maxChars);
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        return rtrim(mb_substr($text, 0, max(1, $limit - 1))) . '…';
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

    private function noDataSvg(string $message): string
    {
        $msg = htmlspecialchars($message);
        return '<svg viewBox="0 0 400 120" xmlns="http://www.w3.org/2000/svg" width="100%" height="100%" preserveAspectRatio="xMinYMin meet" style="display:block">
            <text x="200" y="60" text-anchor="middle" dominant-baseline="middle" fill="#9CA3AF" font-size="14">' . $msg . '</text>
        </svg>';
    }
}
