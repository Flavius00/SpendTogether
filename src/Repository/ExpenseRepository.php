<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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
     * @param User[] $users
     *
     * @return array{items: Expense[], total: int, page: int, pages: int, perPage: int}
     */
    public function searchByUsers(array $users, array $criteria, string $sort, string $dir, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('e')
            ->leftJoin('e.categoryId', 'c')
            ->addSelect('c');

        $count = count($users);

        if ($count === 0) {
            // no users -> nothing
            $qb->andWhere('1 = 0');

            return [
                'items' => [],
                'total' => 0,
                'page' => $page,
                'pages' => 1,
                'perPage' => $perPage,
            ];
        }

        if ($count === 1) {
            $qb->andWhere('e.userObject = :user')->setParameter('user', $users[0]);
            $this->applyCriteriaSingle($qb, $criteria);
            $this->applySorting($qb, $sort, $dir);
        } else {
            $qb->andWhere('e.userObject IN (:users)')->setParameter('users', $users);
            $this->applyCriteriaMulti($qb, $criteria);
            $this->applySorting($qb, $sort, $dir);
        }

        // Total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(e.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

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
     * Deprecated: use searchByUsers([$user], ...).
     *
     * @return array{items: Expense[], total: int, page: int, pages: int, perPage: int}
     */
    public function searchForUser(User $user, array $criteria, string $sortBy, string $direction, int $page, int $perPage): array {
        return $this->searchByUsers([$user], $criteria, $sortBy, $direction, $page, $perPage);
    }

    /**
     * Deprecated: use searchByUsers($users, ...).
     *
     * @param User[] $users
     *
     * @return array{items: Expense[], total: int, page: int, pages: int, perPage: int}
     */
    public function searchForUsers(array $users, array $criteria, string $sort, string $dir, int $page, int $perPage): array {
        return $this->searchByUsers($users, $criteria, $sort, $dir, $page, $perPage);
    }

    private function applyCriteriaSingle(QueryBuilder $qb, array $criteria): void
    {
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
                $qb->andWhere('e.date >= :df')->setParameter('df', $df);
            } catch (\Throwable) {
                // ignore
            }
        }
        if (!empty($criteria['date_to'])) {
            try {
                $dt = new \DateTime((string) $criteria['date_to']);
                $qb->andWhere('e.date <= :dt')->setParameter('dt', $dt);
            } catch (\Throwable) {
                // ignore
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
                $qb->andWhere("e.receiptImage IS NOT NULL AND e.receiptImage <> ''");
            } else {
                $qb->andWhere("e.receiptImage IS NULL OR e.receiptImage = ''");
            }
        }
    }

    private function applyCriteriaMulti(QueryBuilder $qb, array $criteria): void
    {
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
    }

    private function applySorting(QueryBuilder $qb, string $sort, string $dir): void
    {
        $sortMap = [
            'date' => 'e.date',
            'name' => 'e.name',
            'amount' => 'e.amount',
            'category' => 'c.name',
        ];
        $sortBy = $sortMap[$sort] ?? 'e.date';
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy($sortBy, $direction)->addOrderBy('e.id', 'DESC');
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
