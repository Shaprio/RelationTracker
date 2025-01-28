<?php

namespace App\Repository;

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

}
