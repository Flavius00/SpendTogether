<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Subscription;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Subscription>
 */
class SubscriptionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Subscription::class);
    }

    /**
     * @param User[] $users
     *
     * @return array{items: Subscription[], total: int, page: int, pages: int, perPage: int}
     */
    public function searchByUsers(array $users, array $criteria, string $sort, string $dir, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.category', 'c')
            ->leftJoin('s.userObject', 'u')
            ->addSelect('c', 'u');

        if (!$users) {
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

        $qb->andWhere('s.userObject IN (:users)')->setParameter('users', $users);

        $this->applyCriteria($qb, $criteria);
        $this->applySorting($qb, $sort, $dir);

        // Total
        $countQb = clone $qb;
        $total = (int) $countQb->select('COUNT(s.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

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
     */
    public function searchForUser(User $user, array $criteria, string $sort, string $dir, int $page, int $perPage): array
    {
        return $this->searchByUsers([$user], $criteria, $sort, $dir, $page, $perPage);
    }

    /**
     * Deprecated: use searchByUsers($users, ...).
     *
     * @param User[] $users
     */
    public function searchForUsers(array $users, array $criteria, string $sort, string $dir, int $page, int $perPage): array
    {
        return $this->searchByUsers($users, $criteria, $sort, $dir, $page, $perPage);
    }

    private function applyCriteria(QueryBuilder $qb, array $criteria): void
    {
        if (!empty($criteria['q'])) {
            $qb->andWhere('LOWER(s.name) LIKE :q')->setParameter('q', '%' . mb_strtolower($criteria['q']) . '%');
        }
        if (!empty($criteria['category'])) {
            $qb->andWhere('s.category = :cat')->setParameter('cat', $criteria['category']);
        }
        if (!empty($criteria['frequency'])) {
            $qb->andWhere('s.frequency = :freq')->setParameter('freq', $criteria['frequency']);
        }
        if (array_key_exists('active', $criteria) && $criteria['active'] !== null) {
            $qb->andWhere('s.isActive = :act')->setParameter('act', (bool) $criteria['active']);
        }
        if (!empty($criteria['next_from'])) {
            $qb->andWhere('s.nextDueDate >= :dfrom')->setParameter('dfrom', new \DateTime($criteria['next_from'] . ' 00:00:00'));
        }
        if (!empty($criteria['next_to'])) {
            $qb->andWhere('s.nextDueDate <= :dto')->setParameter('dto', new \DateTime($criteria['next_to'] . ' 23:59:59'));
        }
    }

    private function applySorting(QueryBuilder $qb, string $sort, string $dir): void
    {
        $sortMap = [
            'next_due' => 's.nextDueDate',
            'name' => 's.name',
            'amount' => 's.amount',
            'category' => 'c.name',
            'user' => 'u.email',
        ];
        $sortBy = $sortMap[$sort] ?? 's.nextDueDate';
        $direction = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';

        $qb->orderBy($sortBy, $direction)->addOrderBy('s.id', 'DESC');
    }

    //    /**
    //     * @return Subscription[] Returns an array of Subscription objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('s.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Subscription
    //    {
    //        return $this->createQueryBuilder('s')
    //            ->andWhere('s.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
