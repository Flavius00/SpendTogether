<?php

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Expense>
 */
class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    /**
     * Pagination list for multiple users (e.g. All family).
     *
     * @param User[] $users
     * @return array{items: Expense[], total: int, page: int, pages: int, perPage: int}
     */
    public function searchForUsers(array $users, array $criteria, string $sort, string $dir, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.categoryId', 'c')
            ->addSelect('c');

        if ($users) {
            $qb->andWhere('e.userObject IN (:users)')->setParameter('users', $users);
        } else {
            // no users -> we return nothing
            $qb->andWhere('1 = 0');
        }

        // Filters
        if (!empty($criteria['q'])) {
            $qb->andWhere('LOWER(e.name) LIKE :q OR LOWER(e.description) LIKE :q')
                ->setParameter('q', '%' . mb_strtolower($criteria['q']) . '%');
        }
        if (!empty($criteria['category'])) {
            $qb->andWhere('e.categoryId = :cat')->setParameter('cat', $criteria['category']);
        }
        if (!empty($criteria['date_from'])) {
            $qb->andWhere('e.date >= :dfrom')->setParameter('dfrom', new \DateTime($criteria['date_from'] . ' 00:00:00'));
        }
        if (!empty($criteria['date_to'])) {
            $qb->andWhere('e.date <= :dto')->setParameter('dto', new \DateTime($criteria['date_to'] . ' 23:59:59'));
        }
        if ($criteria['min_amount'] !== null && $criteria['min_amount'] !== '') {
            $qb->andWhere('e.amount >= :min')->setParameter('min', $criteria['min_amount']);
        }
        if ($criteria['max_amount'] !== null && $criteria['max_amount'] !== '') {
            $qb->andWhere('e.amount <= :max')->setParameter('max', $criteria['max_amount']);
        }
        if (array_key_exists('has_receipt', $criteria) && $criteria['has_receipt'] !== null) {
            if ($criteria['has_receipt'] === true) {
                $qb->andWhere('e.receiptImage IS NOT NULL');
            } elseif ($criteria['has_receipt'] === false) {
                $qb->andWhere('e.receiptImage IS NULL');
            }
        }

        // Sorting
        $sortMap = [
            'date' => 'e.date',
            'name' => 'e.name',
            'amount' => 'e.amount',
            'category' => 'c.name',
        ];
        $sortBy = $sortMap[$sort] ?? 'e.date';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($sortBy, $dir)->addOrderBy('e.id', 'DESC');

        // Total
        $countQb = clone $qb;
        $countQb->resetDQLPart('orderBy')->select('COUNT(e.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        // Pagination
        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);
        $items = $qb->getQuery()->getResult();

        $pages = max(1, (int) ceil($total / $perPage));

        return [
            'items' => $items,
            'total' => $total,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
        ];
    }

    /**
     * @param array{
     *     q?: string|null,
     *     category?: int|null,
     *     date_from?: string|null,
     *     date_to?: string|null,
     *     min_amount?: string|float|int|null,
     *     max_amount?: string|float|int|null,
     *     has_receipt?: bool|null
     * } $criteria
     * @return array{
     *     items: array<int, Expense>,
     *     total: int,
     *     page: int,
     *     pages: int,
     *     perPage: int
     * }
     */
    public function searchForUser(
        User $user,
        array $criteria,
        string $sortBy,
        string $direction,
        int $page,
        int $perPage
    ): array {
        $allowedSorts = ['date', 'amount', 'name', 'category'];
        if (!in_array($sortBy, $allowedSorts, true)) {
            $sortBy = 'date';
        }
        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';

        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.categoryId', 'c')
            ->addSelect('c')
            ->andWhere('e.userObject = :user')
            ->setParameter('user', $user);

        if (!empty($criteria['q'])) {
            $qb->andWhere('e.name LIKE :q OR e.description LIKE :q')
                ->setParameter('q', '%' . trim((string) $criteria['q']) . '%');
        }
        if (!empty($criteria['category'])) {
            $qb->andWhere('c.id = :cat')
                ->setParameter('cat', (int) $criteria['category']);
        }
        if (!empty($criteria['date_from'])) {
            try {
                $df = new \DateTime((string) $criteria['date_from']);
                $qb->andWhere('e.date >= :df')
                    ->setParameter('df', $df);
            } catch (\Throwable) {
                // ignore invalid date
            }
        }
        if (!empty($criteria['date_to'])) {
            try {
                $dt = new \DateTime((string) $criteria['date_to']);
                $qb->andWhere('e.date <= :dt')
                    ->setParameter('dt', $dt);
            } catch (\Throwable) {
                // ignore invalid date
            }
        }
        if ($criteria['min_amount'] !== null && $criteria['min_amount'] !== '') {
            $qb->andWhere('e.amount >= :minA')
                ->setParameter('minA', number_format((float) $criteria['min_amount'], 2, '.', ''));
        }
        if ($criteria['max_amount'] !== null && $criteria['max_amount'] !== '') {
            $qb->andWhere('e.amount <= :maxA')
                ->setParameter('maxA', number_format((float) $criteria['max_amount'], 2, '.', ''));
        }
        if ($criteria['has_receipt'] !== null) {
            if ($criteria['has_receipt']) {
                $qb->andWhere('e.receiptImage IS NOT NULL AND e.receiptImage <> \'\'');
            } else {
                $qb->andWhere('e.receiptImage IS NULL OR e.receiptImage = \'\'');
            }
        }

        // Sorting
        switch ($sortBy) {
            case 'amount':
                $qb->addOrderBy('e.amount', $direction)->addOrderBy('e.id', 'DESC');
                break;
            case 'name':
                $qb->addOrderBy('e.name', $direction)->addOrderBy('e.id', 'DESC');
                break;
            case 'category':
                $qb->addOrderBy('c.name', $direction)->addOrderBy('e.id', 'DESC');
                break;
            case 'date':
            default:
                $qb->addOrderBy('e.date', $direction)->addOrderBy('e.id', 'DESC');
                break;
        }

        // Count total
        $countQb = clone $qb;
        $count = (int) $countQb->select('COUNT(e.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);
        $items = $qb->getQuery()->getResult();

        $pages = max(1, (int) ceil($count / $perPage));

        return [
            'items' => $items,
            'total' => $count,
            'page' => $page,
            'pages' => $pages,
            'perPage' => $perPage,
        ];
    }


    //    /**
    //     * @return Expense[] Returns an array of Expense objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('e.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Expense
    //    {
    //        return $this->createQueryBuilder('e')
    //            ->andWhere('e.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
