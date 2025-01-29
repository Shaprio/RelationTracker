<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Bundle\SecurityBundle\Security;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Entity\Event;
use App\Entity\RecurringEvent;

class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'notifications')]
    public function notifications(EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        // Spotkania o wysokim priorytecie
        $importantMeetings = $entityManager->getRepository(Event::class)->findBy([
            'userE' => $user,
            'isImportant' => true,
        ]);

        $meetings = array_map(function ($meeting) {
            return [
                'type' => 'Meeting',
                'title' => $meeting->getTitle(),
                'date' => $meeting->getDate(),
                'description' => $meeting->getDescription(),
            ];
        }, $importantMeetings);

        // Powtarzające się wydarzenia o wysokim priorytecie
        $importantRecurringEvents = $entityManager->getRepository(RecurringEvent::class)->findBy([
            'owner' => $user,
            'isImportant' => true,
        ]);

        $recurringEvents = array_map(function ($recurringEvent) {
            return [
                'type' => 'Recurring Event',
                'title' => $recurringEvent->getTitle(),
                'date' => $recurringEvent->getStartDate(),
                'description' => $recurringEvent->getDescription(),
            ];
        }, $importantRecurringEvents);

        // Scal i sortuj wg daty
        $allEvents = array_merge($meetings, $recurringEvents);

        usort($allEvents, function ($a, $b) {
            return $a['date'] <=> $b['date'];
        });

        return $this->render('notifications.html.twig', [
            'events' => $allEvents,
        ]);
    }
}
