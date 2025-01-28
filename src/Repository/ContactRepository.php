<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\Contact;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Contact::class);
    }

    public function findStaleContacts(User $user, int $days = 30): array
    {
        $cutoff = (new \DateTime())->modify("-{$days} days");

        return $this->createQueryBuilder('c')
            ->andWhere('c.userName = :user') // lub 'owner' -> dopasuj do nazwy w encji
            ->andWhere('c.lastInteraction < :cutoff OR c.lastInteraction IS NULL')
            ->setParameter('user', $user)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('c.lastInteraction', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
