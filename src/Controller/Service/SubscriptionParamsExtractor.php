<?php

declare(strict_types=1);

namespace App\Controller\Service;

use Symfony\Component\HttpFoundation\Request;

final class SubscriptionParamsExtractor
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
    public function extractSorting(Request $request, array $allowedSorts, string $defaultSort, string $defaultDir = 'ASC'): array
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
     *   frequency: string|null,
     *   active: bool|null,
     *   next_from: string|null,
     *   next_to: string|null
     * }
     */
    public function extractCriteria(Request $request): array
    {
        $q = trim((string) $request->query->get('q', '')) ?: null;

        $categoryRaw = $request->query->get('category');
        $category = ($categoryRaw === null || $categoryRaw === '') ? null : (int) $categoryRaw;

        $frequencyRaw = $request->query->get('frequency');
        $frequency = ($frequencyRaw === null || $frequencyRaw === '') ? null : (string) $frequencyRaw;

        $activeRaw = $request->query->get('active');
        $active = null;
        if ($activeRaw !== null && $activeRaw !== '') {
            $active = $activeRaw === '1';
        }

        $nextFrom = $request->query->get('next_from') ?: null;
        $nextTo = $request->query->get('next_to') ?: null;

        return [
            'q' => $q,
            'category' => $category,
            'frequency' => $frequency,
            'active' => $active,
            'next_from' => $nextFrom,
            'next_to' => $nextTo,
        ];
    }
}
