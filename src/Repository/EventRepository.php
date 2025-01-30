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

    public function findUpcomingForUser(User $user, \DateTime $fromDate, \DateTime $toDate): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.userE = :user')
            ->andWhere('e.date BETWEEN :fromDate AND :toDate')
            ->setParameter('user', $user)
            ->setParameter('fromDate', $fromDate->format('Y-m-d 00:00:00'))
            ->setParameter('toDate', $toDate->format('Y-m-d 23:59:59'))
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByDateForUser(User $user, \DateTimeInterface $date): array
    {
        return $this->createQueryBuilder('e')
            ->andWhere('e.userE = :user')
            ->andWhere('e.date >= :selectedDateStart')
            ->andWhere('e.date < :selectedDateEnd')
            ->setParameter('user', $user)
            ->setParameter('selectedDateStart', $date->format('Y-m-d 00:00:00'))
            ->setParameter('selectedDateEnd', $date->format('Y-m-d 23:59:59'))
            ->orderBy('e.date', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
