<?php

namespace App\Controller;

use DateTime;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use OpenApi\Attributes as OA;
use App\Entity\User;
use App\Entity\RecurringEvent;
use App\Entity\RecurringEventContact;
use App\Entity\Contact;

class RecurringEventController extends AbstractController
{


    #[Route('/recurring-events', name: 'recurring_events', methods: ['GET', 'POST'])]
    public function recurringEvents(Request $request, EntityManagerInterface $entityManager): Response
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $recurringEvents = $entityManager
            ->getRepository(RecurringEvent::class)
            ->findBy(['owner' => $user]);

        $contacts = $entityManager->getRepository(Contact::class)
            ->findBy(['userName' => $user]);

        if ($request->isMethod('POST')) {
            $eventId = $request->request->get('event_id');
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $recurrencePattern = $request->request->get('recurrence_pattern');
            $startDate = $request->request->get('start_date');
            $endDate = $request->request->get('end_date');
            $selectedContacts = $request->request->all('contacts');

            if ($eventId) {
                // Edycja
                $event = $entityManager->getRepository(RecurringEvent::class)->find($eventId);
                $event->setTitle($title)
                    ->setDescription($description)
                    ->setRecurrencePattern($recurrencePattern)
                    ->setStartDate(new DateTime($startDate))
                    ->setEndDate($endDate ? new DateTime($endDate) : null)
                    ->setUpdatedAt(new DateTime());

                // Usunięcie poprzednich kontaktów
                foreach ($event->getContacts() as $existingContact) {
                    $entityManager->remove($existingContact);
                }

                // Dodanie nowych kontaktów
                foreach ($selectedContacts as $contactId) {
                    $contact = $entityManager->getRepository(Contact::class)->find($contactId);
                    if ($contact) {
                        $recurringEventContact = new RecurringEventContact();
                        $recurringEventContact->setRecurringEvent($event);
                        $recurringEventContact->setContact($contact);
                        $entityManager->persist($recurringEventContact);
                    }
                }
            } else {
                // Dodanie nowego
                $event = new RecurringEvent();
                $event->setTitle($title)
                    ->setDescription($description)
                    ->setRecurrencePattern($recurrencePattern)
                    ->setStartDate(new DateTime($startDate))
                    ->setEndDate($endDate ? new DateTime($endDate) : null)
                    ->setOwner($user)
                    ->setCreatedAt(new DateTimeImmutable())
                    ->setUpdatedAt(new DateTime());

                $entityManager->persist($event);

                foreach ($selectedContacts as $contactId) {
                    $contact = $entityManager->getRepository(Contact::class)->find($contactId);
                    if ($contact) {
                        $recurringEventContact = new RecurringEventContact();
                        $recurringEventContact->setRecurringEvent($event);
                        $recurringEventContact->setContact($contact);
                        $entityManager->persist($recurringEventContact);
                    }
                }
            }

            $entityManager->flush();
            return $this->redirectToRoute('recurring_events');
        }

        return $this->render('recurring-events.html.twig', [
            'events' => $recurringEvents,
            'contacts' => $contacts,
        ]);
    }

    #[Route('/recurring-events/{id}/details', name: 'recurring_event_details', methods: ['GET'])]
    public function getRecurringEventDetails(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $event = $entityManager->getRepository(RecurringEvent::class)->find($id);
        if (!$event) {
            return new JsonResponse(['error' => 'Event not found'], 404);
        }

        return new JsonResponse([
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'recurrencePattern' => $event->getRecurrencePattern(),
            'startDate' => $event->getStartDate()->format('Y-m-d'),
            'isImportant' => $event->getIsImportant(),
            'contacts' => array_map(
                fn($recurringEventContact) => [
                    'id' => $recurringEventContact->getContact()->getId(), // Pobranie Contact z RecurringEventContact
                    'name' => $recurringEventContact->getContact()->getName(), // Pobranie nazwy kontaktu
                ],
                $event->getContacts()->toArray()
            ),
        ]);
    }

    #[Route('/recurring-events/{id}/delete', name: 'recurring_event_delete', methods: ['POST'])]
    public function deleteRecurringEvent(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $event = $entityManager->getRepository(RecurringEvent::class)->find($id);
        if (!$event) {
            return new JsonResponse(['error' => 'Event not found'], 404);
        }

        $entityManager->remove($event);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/recurring-events/{id}/important', name: 'recurring_event_important', methods: ['POST'])]
    public function toggleImportantRecurringEvent(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $event = $entityManager->getRepository(RecurringEvent::class)->find($id);
        if (!$event) {
            return new JsonResponse(['error' => 'Event not found'], 404);
        }

        $event->setIsImportant(!$event->getIsImportant());
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'isImportant' => $event->getIsImportant()]);
    }


    #[Route('/api/recurring-events', name: 'api_recurring_events', methods: ['GET'])]
    #[OA\Get(
        path: "/api/recurring-events",
        summary: "Get user's recurring events",
        security: [["Bearer" => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: "List of recurring events",
                content: new OA\JsonContent(
                    type: "array",
                    items: new OA\Items(ref: "#/components/schemas/RecurringEvent")
                )
            ),
            new OA\Response(response: 401, description: "Unauthorized")
        ]
    )]
    public function apiGetRecurringEvents(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return new JsonResponse(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $events = $entityManager->getRepository(RecurringEvent::class)->findBy(['owner' => $user]);
        $data = array_map(fn($event) => [
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'recurrencePattern' => $event->getRecurrencePattern(),
            'startDate' => $event->getStartDate()?->format('Y-m-d'),
            'isImportant' => $event->getIsImportant(),
            'contacts' => array_map(fn($contact) => [
                'id' => $contact->getId(),
                'name' => $contact->getName(),
            ], $event->getContacts()->toArray()),
        ], $events);

        return new JsonResponse($data);
    }


    #[Route('/api/recurring-events/{id}/details', name: 'api_recurring_event_details', methods: ['GET'])]
    #[OA\Get(
        path: "/api/recurring-events/{id}/details",
        summary: "Get details of a specific recurring event",
        security: [["Bearer" => []]],
        responses: [
            new OA\Response(response: 200, description: "Recurring event details"),
            new OA\Response(response: 404, description: "Event not found")
        ]
    )]
    public function apiGetRecurringEventDetails(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $event = $entityManager->getRepository(RecurringEvent::class)->find($id);
        if (!$event) {
            return new JsonResponse(['error' => 'Event not found'], 404);
        }

        return new JsonResponse([
            'id' => $event->getId(),
            'title' => $event->getTitle(),
            'description' => $event->getDescription(),
            'recurrencePattern' => $event->getRecurrencePattern(),
            'startDate' => $event->getStartDate()?->format('Y-m-d'),
            'isImportant' => $event->getIsImportant(),
            'contacts' => array_map(fn($contact) => [
                'id' => $contact->getId(),
                'name' => $contact->getName(),
            ], $event->getContacts()->toArray()),
        ]);
    }


    #[Route('/api/recurring-events/{id}/delete', name: 'api_recurring_event_delete', methods: ['DELETE'])]
    #[OA\Delete(
        path: "/api/recurring-events/{id}/delete",
        summary: "Delete a recurring event",
        security: [["Bearer" => []]],
        responses: [
            new OA\Response(response: 200, description: "Recurring event deleted"),
            new OA\Response(response: 404, description: "Event not found")
        ]
    )]
    public function apiDeleteRecurringEvent(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $event = $entityManager->getRepository(RecurringEvent::class)->find($id);
        if (!$event) {
            return new JsonResponse(['error' => 'Event not found'], 404);
        }

        $entityManager->remove($event);
        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }


    #[Route('/api/recurring-events/{id}/important', name: 'api_recurring_event_important', methods: ['PATCH'])]
    #[OA\Patch(
        path: "/api/recurring-events/{id}/important",
        summary: "Toggle important status for a recurring event",
        security: [["Bearer" => []]],
        responses: [
            new OA\Response(response: 200, description: "Event updated"),
            new OA\Response(response: 404, description: "Event not found")
        ]
    )]
    public function apiToggleImportantRecurringEvent(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $event = $entityManager->getRepository(RecurringEvent::class)->find($id);
        if (!$event) {
            return new JsonResponse(['error' => 'Event not found'], 404);
        }

        $event->setIsImportant(!$event->getIsImportant());
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'isImportant' => $event->getIsImportant()]);
    }
}
