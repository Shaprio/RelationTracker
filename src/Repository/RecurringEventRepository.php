<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\RecurringEvent;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RecurringEvent>
 *
 * @method RecurringEvent|null find($id, $lockMode = null, $lockVersion = null)
 * @method RecurringEvent|null findOneBy(array $criteria, array $orderBy = null)
 * @method RecurringEvent[]    findAll()
 * @method RecurringEvent[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class RecurringEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecurringEvent::class);
    }

    public function findThisWeekForUser(User $user): array
    {
        $startOfWeek = (new \DateTime('monday this week'))->setTime(0, 0);
        $endOfWeek   = (new \DateTime('sunday this week'))->setTime(23, 59);

        return $this->createQueryBuilder('r')
            ->andWhere('r.owner = :owner')
            ->andWhere('r.startDate BETWEEN :start AND :end')
            ->setParameter('owner', $user)
            ->setParameter('start', $startOfWeek)
            ->setParameter('end', $endOfWeek)
            ->orderBy('r.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findBirthdaysThisMonth(User $user): array
    {
        $startOfMonth = (new \DateTime('first day of this month'))->setTime(0, 0);
        $endOfMonth   = (new \DateTime('last day of this month'))->setTime(23, 59);

        return $this->createQueryBuilder('r')
            ->andWhere('r.owner = :owner')
            ->andWhere('r.title LIKE :title')
            ->andWhere('r.startDate BETWEEN :start AND :end')
            ->setParameter('owner', $user)
            ->setParameter('title', '%Birthday%')
            ->setParameter('start', $startOfMonth)
            ->setParameter('end', $endOfMonth)
            ->orderBy('r.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findTodayForUser(User $user): array
    {
        $todayStart = (new \DateTime())->setTime(0, 0);
        $todayEnd   = (new \DateTime())->setTime(23, 59);

        return $this->createQueryBuilder('r')
            ->andWhere('r.owner = :owner')
            ->andWhere('r.startDate BETWEEN :start AND :end')
            ->setParameter('owner', $user)
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->orderBy('r.startDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
