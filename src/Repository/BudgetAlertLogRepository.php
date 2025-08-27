<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\BudgetAlertLog;
use App\Entity\Category;
use App\Entity\Family;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<BudgetAlertLog>
 */
final class BudgetAlertLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BudgetAlertLog::class);
    }

    public function existsForFamilyMonthAmount(Family $family, string $type, string $month, float $projected): bool
    {
        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.family = :f')
            ->andWhere('b.type = :t')
            ->andWhere('b.month = :m')
            ->andWhere('b.projectedAmount = :p')
            ->andWhere('b.category IS NULL')
            ->setParameter('f', $family)
            ->setParameter('t', $type)
            ->setParameter('m', $month)
            ->setParameter('p', number_format($projected, 2, '.', ''));

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function existsForFamilyMonthAmountAndCategory(Family $family, string $type, string $month, float $amount, Category $category): bool
    {
        $qb = $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.family = :f')
            ->andWhere('b.type = :t')
            ->andWhere('b.month = :m')
            ->andWhere('b.projectedAmount = :p')
            ->andWhere('b.category = :c')
            ->setParameter('f', $family)
            ->setParameter('t', $type)
            ->setParameter('m', $month)
            ->setParameter('p', number_format($amount, 2, '.', ''))
            ->setParameter('c', $category);

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

//    /**
//     * @return BudgetAlertLog[] Returns an array of BudgetAlertLog objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('b.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?BudgetAlertLog
//    {
//        return $this->createQueryBuilder('b')
//            ->andWhere('b.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
