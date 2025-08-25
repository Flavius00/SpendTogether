<?php

declare(strict_types=1);

namespace App\Controller\Service;

use Symfony\Component\HttpFoundation\Request;

final class ExpenseParamsExtractor
{
    /**
     * @return array{page:int, perPage:int}
     */
    public function extractPagination(Request $request, int $defaultPerPage = 20, int $maxPerPage = 100): array
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPageRaw = $request->query->get('perPage', $defaultPerPage);
        $perPage = min($maxPerPage, max(5, (int) ($perPageRaw === '' ? $defaultPerPage : $perPageRaw)));

        return ['page' => $page, 'perPage' => $perPage];
    }

    /**
     * @param list<string> $allowedSorts
     *
     * @return array{sort:string, dir:'ASC'|'DESC'}
     */
    public function extractSorting(Request $request, array $allowedSorts, string $defaultSort, string $defaultDir = 'DESC'): array
    {
        $sort = (string) $request->query->get('sort', $defaultSort);
        if (!in_array($sort, $allowedSorts, true)) {
            $sort = $defaultSort;
        }

        $dir = strtoupper((string) $request->query->get('dir', $defaultDir));
        $dir = $dir === 'ASC' ? 'ASC' : 'DESC';

        return ['sort' => $sort, 'dir' => $dir];
    }

    /**
     * @return array{
     *   q: string|null,
     *   category: int|null,
     *   date_from: string|null,
     *   date_to: string|null,
     *   min_amount: float|int|string|null,
     *   max_amount: float|int|string|null,
     *   has_receipt: bool|null
     * }
     */
    public function extractCriteria(Request $request): array
    {
        $q = trim((string) $request->query->get('q', '')) ?: null;

        $categoryRaw = $request->query->get('category');
        $category = ($categoryRaw === null || $categoryRaw === '') ? null : ($request->query->getInt('category') ?: null);

        $minAmountRaw = $request->query->get('min_amount');
        $minAmount = ($minAmountRaw === null || $minAmountRaw === '') ? null : (float) $minAmountRaw;

        $maxAmountRaw = $request->query->get('max_amount');
        $maxAmount = ($maxAmountRaw === null || $maxAmountRaw === '') ? null : (float) $maxAmountRaw;

        $dateFrom = $request->query->get('date_from') ?: null;
        $dateTo = $request->query->get('date_to') ?: null;

        $hasReceiptRaw = $request->query->get('has_receipt');
        $hasReceipt = null;
        if ($hasReceiptRaw !== null && $hasReceiptRaw !== '') {
            $val = strtolower((string) $hasReceiptRaw);
            if ($val === '1' || $val === 'true') {
                $hasReceipt = true;
            } elseif ($val === '0' || $val === 'false') {
                $hasReceipt = false;
            }
        }

        return [
            'q' => $q,
            'category' => $category,
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'min_amount' => $minAmount,
            'max_amount' => $maxAmount,
            'has_receipt' => $hasReceipt,
        ];
    }
}
