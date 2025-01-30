<?php

namespace App\Repository;

use App\Entity\RecurringEvent;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RecurringEventRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RecurringEvent::class);
    }

    public function findRecurringInNextDays(User $user, int $daysAhead): array
    {
        $start = new \DateTime();
        $end = (new \DateTime())->modify("+$daysAhead days");

        $recurringEvents = $this->createQueryBuilder('r')
            ->andWhere('r.owner = :owner')
            ->setParameter('owner', $user)
            ->getQuery()
            ->getResult();

        $matchingEvents = [];

        foreach ($recurringEvents as $event) {
            $currentDate = clone $start;

            while ($currentDate <= $end) {
                if ($this->isRecurringOnDate($event, $currentDate)) {
                    $matchingEvents[] = [
                        'title' => $event->getTitle(),
                        'date' => $currentDate->format('Y-m-d'), // Aktualna data powtórzenia
                        'type' => 'Recurring'
                    ];
                }
                $currentDate->modify('+1 day'); // Sprawdzamy kolejny dzień
            }
        }

        return $matchingEvents;
    }

    public function findRecurringOnDate(User $user, \DateTimeInterface $date): array
    {
        $recurringEvents = $this->createQueryBuilder('r')
            ->andWhere('r.owner = :owner')
            ->setParameter('owner', $user)
            ->orderBy('r.startDate', 'ASC')
            ->getQuery()
            ->getResult();

        return array_filter($recurringEvents, function ($event) use ($date) {
            return $this->isRecurringOnDate($event, $date);
        });
    }

    public function findBirthdaysThisMonthForUser(User $user): array
    {
        $startOfMonth = (new \DateTime('first day of this month'))->setTime(0, 0);
        $endOfMonth   = (new \DateTime('last day of this month'))->setTime(23, 59);

        $recurringEvents = $this->createQueryBuilder('r')
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

        return $recurringEvents;
    }

    private function isRecurringOnDate(RecurringEvent $event, \DateTimeInterface $date): bool
    {
        $startDate = clone $event->getStartDate(); // Klonujemy, żeby nie nadpisywać oryginału

        switch ($event->getRecurrencePattern()) {
            case 'daily':
                return $startDate <= $date;
            case 'weekly':
                return $startDate <= $date && $startDate->format('N') === $date->format('N');
            case 'monthly':
                return $startDate <= $date && $startDate->format('d') === $date->format('d');
            case 'yearly':
                return $startDate <= $date && $startDate->format('m-d') === $date->format('m-d');
            default:
                return false;
        }
    }

    private function isRecurringBetweenDates(RecurringEvent $event, \DateTimeInterface $start, \DateTimeInterface $end): bool
    {
        $date = clone $start;
        while ($date <= $end) {
            if ($this->isRecurringOnDate($event, $date)) {
                return true;
            }
            $date->modify('+1 day');
        }
        return false;
    }

    public function getRecurringEventsOnDate(User $user, \DateTimeInterface $date): array
    {
        $recurringEvents = $this->createQueryBuilder('r')
            ->andWhere('r.owner = :owner')
            ->setParameter('owner', $user)
            ->getQuery()
            ->getResult();

        $matchingEvents = [];
        foreach ($recurringEvents as $event) {
            if ($this->isRecurringOnDate($event, $date)) {
                $matchingEvents[] = [
                    'title' => $event->getTitle(),
                    'date' => $date->format('Y-m-d'), // Zmieniamy datę na rzeczywistą powtórzoną
                    'type' => 'Recurring'
                ];
            }
        }
        return $matchingEvents;
    }
}
