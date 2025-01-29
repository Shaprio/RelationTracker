<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Entity\Event;
use App\Entity\RecurringEvent;
use App\Entity\Contact;

class DashboardController extends AbstractController
{
    #[Route('/mainPage', name: 'mainPage', methods: ['GET'])]
    public function mainPage(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        // 1. Meetingi (Event) w tym tygodniu
        $eventsThisWeek = $entityManager
            ->getRepository(Event::class)
            ->findThisWeekForUser($user);

        // 2. Recurring events w tym tygodniu
        $recurringThisWeek = $entityManager
            ->getRepository(RecurringEvent::class)
            ->findThisWeekForUser($user);

        // 3. Recurring events o tytule „Birthday” w tym miesiącu
        $birthdayEvents = $entityManager
            ->getRepository(RecurringEvent::class)
            ->findBirthdaysThisMonth($user);

        // 4. Kontakty, z którymi nie było interakcji ponad miesiąc
        $staleContacts = $entityManager
            ->getRepository(Contact::class)
            ->findStaleContacts($user);

        // 5. Dzisiejsze wydarzenia (Meetings + RecurringEvents)
        $todayEventsMeetings = $entityManager
            ->getRepository(Event::class)
            ->findTodayForUser($user);

        $todayEventsRecurring = $entityManager
            ->getRepository(RecurringEvent::class)
            ->findTodayForUser($user);

        $todayEvents = array_merge($todayEventsMeetings, $todayEventsRecurring);

        return $this->render('mainPage.html.twig', [
            'eventsThisWeek'    => $eventsThisWeek,
            'recurringThisWeek' => $recurringThisWeek,
            'birthdayEvents'    => $birthdayEvents,
            'staleContacts'     => $staleContacts,
            'todayEvents'       => $todayEvents,
        ]);
    }
}
