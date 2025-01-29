<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Entity\Contact;
use App\Entity\Event;
use App\Entity\RecurringEvent;
use App\Entity\Interaction;

class StatisticsController extends AbstractController
{
    #[Route('/statistics', name: 'statistics', methods: ['GET'])]
    public function statistics(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $contactCount = $entityManager->getRepository(Contact::class)
            ->count(['userName' => $user]);

        $eventCount = $entityManager->getRepository(Event::class)
            ->count(['userE' => $user]);

        $recurringEventCount = $entityManager->getRepository(RecurringEvent::class)
            ->count(['owner' => $user]);

        // Zlicz interakcje
        $interactionCount = $entityManager->createQuery("
            SELECT COUNT(i.id)
            FROM App\Entity\Interaction i
            JOIN i.contact c
            WHERE c.userName = :user
        ")
            ->setParameter('user', $user)
            ->getSingleScalarResult();

        // TOP 5 kontaktów z największą liczbą interakcji
        $topContacts = $entityManager->createQuery("
            SELECT c.name, COUNT(i.id) as interaction_count
            FROM App\Entity\Interaction i
            JOIN i.contact c
            WHERE c.userName = :user
            GROUP BY c.id
            ORDER BY interaction_count DESC
        ")
            ->setParameter('user', $user)
            ->setMaxResults(5)
            ->getResult();

        // TOP 5 kontaktów, z którymi użytkownik inicjuje najwięcej interakcji
        $initiatedByUser = $entityManager->createQuery("
            SELECT c.name, COUNT(i.id) as count
            FROM App\Entity\Interaction i
            JOIN i.contact c
            WHERE c.userName = :user AND i.initiatedBy = 'self'
            GROUP BY c.id
            ORDER BY count DESC
        ")
            ->setParameter('user', $user)
            ->setMaxResults(5)
            ->getResult();

        // TOP 5 kontaktów, które inicjują najwięcej interakcji
        $initiatedByContact = $entityManager->createQuery("
            SELECT c.name, COUNT(i.id) as count
            FROM App\Entity\Interaction i
            JOIN i.contact c
            WHERE c.userName = :user AND i.initiatedBy = 'friend'
            GROUP BY c.id
            ORDER BY count DESC
        ")
            ->setParameter('user', $user)
            ->setMaxResults(5)
            ->getResult();

        return $this->render('statistics.html.twig', [
            'contactCount'        => $contactCount,
            'eventCount'          => $eventCount,
            'recurringEventCount' => $recurringEventCount,
            'interactionCount'    => $interactionCount,
            'topContacts'         => $topContacts,
            'initiatedByUser'     => $initiatedByUser,
            'initiatedByContact'  => $initiatedByContact,
        ]);
    }
}
