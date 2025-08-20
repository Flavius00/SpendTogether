<?php

namespace App\Repository;

use App\Entity\Subscription;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use App\Entity\User;

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
     * @return array{items: Subscription[], total: int, page: int, pages: int, perPage: int}
     */
    public function searchForUsers(array $users, array $criteria, string $sort, string $dir, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('s')
            ->leftJoin('s.category', 'c')
            ->leftJoin('s.userObject', 'u')
            ->addSelect('c', 'u');

        if ($users) {
            $qb->andWhere('s.userObject IN (:users)')->setParameter('users', $users);
        } else {
            $qb->andWhere('1 = 0');
        }

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
            $qb->andWhere('s.isActive = :act')->setParameter('act', (bool)$criteria['active']);
        }
        if (!empty($criteria['next_from'])) {
            $qb->andWhere('s.nextDueDate >= :dfrom')->setParameter('dfrom', new \DateTime($criteria['next_from'] . ' 00:00:00'));
        }
        if (!empty($criteria['next_to'])) {
            $qb->andWhere('s.nextDueDate <= :dto')->setParameter('dto', new \DateTime($criteria['next_to'] . ' 23:59:59'));
        }

        $sortMap = [
            'next_due' => 's.nextDueDate',
            'name' => 's.name',
            'amount' => 's.amount',
            'category' => 'c.name',
            'user' => 'u.email',
        ];
        $sortBy = $sortMap[$sort] ?? 's.nextDueDate';
        $dir = strtoupper($dir) === 'ASC' ? 'ASC' : 'DESC';
        $qb->orderBy($sortBy, $dir)->addOrderBy('s.id', 'DESC');

        // Total
        $countQb = clone $qb;
        $countQb->resetDQLPart('orderBy')->select('COUNT(s.id)');
        $total = (int) $countQb->getQuery()->getSingleScalarResult();

        $qb->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage);
        $items = $qb->getQuery()->getResult();

        $pages = max(1, (int) ceil($total / $perPage));

        return compact('items', 'total', 'page', 'pages', 'perPage');
    }

    public function searchForUser(User $user, array $criteria, string $sort, string $dir, int $page, int $perPage): array
    {
        return $this->searchForUsers([$user], $criteria, $sort, $dir, $page, $perPage);
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
