<?php

namespace App\Repository;

use App\Entity\Interaction;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class InteractionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Interaction::class);
    }

    public function countInteractionsForUser(User $user): int
    {
        return $this->createQueryBuilder('i')
            ->select('COUNT(i.id)')
            ->join('i.contact', 'c')
            ->where('c.userName = :user')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findTopContactsForUser(User $user, int $limit = 3): array
    {
        return $this->createQueryBuilder('i')
            ->select('c.name, COUNT(i.id) as interactionCount')
            ->join('i.contact', 'c')
            ->where('c.userName = :user')
            ->setParameter('user', $user)
            ->groupBy('c.id')
            ->orderBy('interactionCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findTopInitiatedByUser(User $user, int $limit = 3): array
    {
        return $this->createQueryBuilder('i')
            ->select('c.name, COUNT(i.id) as interactionCount')
            ->join('i.contact', 'c')
            ->where('c.userName = :user')
            ->andWhere('i.initiatedBy = :initiator')
            ->setParameter('user', $user)
            ->setParameter('initiator', 'self')
            ->groupBy('c.id')
            ->orderBy('interactionCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findTopInitiatedByContacts(User $user, int $limit = 3): array
    {
        return $this->createQueryBuilder('i')
            ->select('c.name, COUNT(i.id) as interactionCount')
            ->join('i.contact', 'c')
            ->where('c.userName = :user')
            ->andWhere('i.initiatedBy = :initiator')
            ->setParameter('user', $user)
            ->setParameter('initiator', 'friend')
            ->groupBy('c.id')
            ->orderBy('interactionCount', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

