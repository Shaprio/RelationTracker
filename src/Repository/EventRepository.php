<?php

namespace App\Repository;

use App\Entity\Event;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class EventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Event::class);
    }

    public function findThisWeekForUser(User $user): array
    {
        $startOfWeek = (new \DateTime('monday this week'))->setTime(0, 0);
        $endOfWeek   = (new \DateTime('sunday this week'))->setTime(23, 59);

        return $this->createQueryBuilder('e')
            ->andWhere('e.userE = :user')    // <--- kluczowe: userE
            ->andWhere('e.date BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $startOfWeek)
            ->setParameter('end', $endOfWeek)
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
    public function findTodayForUser(User $user): array
    {
        $todayStart = (new \DateTime())->setTime(0, 0);
        $todayEnd   = (new \DateTime())->setTime(23, 59);

        return $this->createQueryBuilder('e')
            ->andWhere('e.userE = :user')  // <-- POPRAWKA
            ->andWhere('e.date BETWEEN :start AND :end')
            ->setParameter('user', $user)
            ->setParameter('start', $todayStart)
            ->setParameter('end', $todayEnd)
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}

