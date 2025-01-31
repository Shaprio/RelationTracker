<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Event;
use App\Entity\RecurringEvent;
use App\Entity\Contact;
use App\Entity\User;
use DateTime;

class DashboardController extends AbstractController
{
    #[Route('/mainPage', name: 'mainPage', methods: ['GET'])]
    public function mainPage(EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        // 🗓 Pobranie wydarzeń na nadchodzące 7 dni (od dzisiaj)
        $startDate = new DateTime();
        $endDate = (new DateTime())->modify('+7 days');

        $upcomingMeetings = $entityManager->getRepository(Event::class)->findUpcomingForUser($user, $startDate, $endDate);
        $upcomingRecurring = $entityManager->getRepository(RecurringEvent::class)->findRecurringInNextDays($user, 7);

        $upcomingEvents = array_merge(
            array_map(fn(Event $event) => [
                'title' => $event->getTitle(),
                'date' => $event->getDate()->format('Y-m-d H:i'),
                'type' => 'Meeting'
            ], $upcomingMeetings),
            $upcomingRecurring
        );

        //  Pobranie wydarzeń na dzisiejszy dzień
        $today = new DateTime();
        $todayMeetings = $entityManager->getRepository(Event::class)->findByDateForUser($user, $today);
        $todayRecurring = $entityManager->getRepository(RecurringEvent::class)->getRecurringEventsOnDate($user, $today);

        $todayEvents = array_merge(
            array_map(fn(Event $event) => [
                'title' => $event->getTitle(),
                'date' => $event->getDate()->format('Y-m-d H:i'),
                'type' => 'Meeting'
            ], $todayMeetings),
            $todayRecurring
        );

        //  Pobranie urodzin w tym miesiącu
        $birthdayEvents = $entityManager->getRepository(RecurringEvent::class)->findBirthdaysThisMonthForUser($user);

        //  Pobranie kontaktów do ponownego kontaktu
        $staleContacts = $entityManager->getRepository(Contact::class)->findStaleContacts($user);

        return $this->render('mainPage.html.twig', [
            'upcomingEvents'  => $upcomingEvents,
            'todayEvents'     => $todayEvents,
            'birthdayEvents'  => $birthdayEvents,
            'staleContacts'   => $staleContacts,
        ]);
    }

    #[Route('/api/today-events', name: 'api_today_events', methods: ['GET'])]
    public function getTodayEvents(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();

        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        // Pobranie wybranej daty (lub dzisiejszej, jeśli brak)
        $dateString = $request->query->get('date', (new DateTime())->format('Y-m-d'));
        $date = DateTime::createFromFormat('Y-m-d', $dateString);

        if (!$date) {
            return $this->json(['error' => 'Invalid date format'], Response::HTTP_BAD_REQUEST);
        }

        // Pobranie wydarzeń dla tej daty
        $todayMeetings = $entityManager->getRepository(Event::class)->findByDateForUser($user, $date);
        $todayRecurring = $entityManager->getRepository(RecurringEvent::class)->getRecurringEventsOnDate($user, $date);

        // Łączenie i formatowanie danych
        $todayEvents = array_merge(
            array_map(fn($event) => [
                'title' => $event->getTitle(),
                'date' => $event->getDate()->format('Y-m-d H:i'),
                'type' => 'Meeting'
            ], $todayMeetings),
            $todayRecurring
        );

        return $this->json($todayEvents);
    }
}
