<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\User;
use App\Entity\RecurringEvent;
use App\Entity\RecurringEventContact;
use App\Entity\Contact;

class RecurringEventController extends AbstractController
{
    #[Route('/recurring-events', name: 'recurring_events', methods: ['GET', 'POST'])]
    public function recurringEvents(Request $request, EntityManagerInterface $entityManager): Response
    {
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
                    ->setStartDate(new \DateTime($startDate))
                    ->setEndDate($endDate ? new \DateTime($endDate) : null)
                    ->setUpdatedAt(new \DateTime());

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
                    ->setStartDate(new \DateTime($startDate))
                    ->setEndDate($endDate ? new \DateTime($endDate) : null)
                    ->setOwner($user)
                    ->setCreatedAt(new \DateTimeImmutable())
                    ->setUpdatedAt(new \DateTime());

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
                fn($contact) => [
                    'id' => $contact->getId(),
                    'name' => $contact->getName(),
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

    // Dodatkowy alias w razie potrzeby (z oryginału):
    // #[Route('/recurring-events/{id}/important', name: 'mark_recurring_event_important', methods: ['POST'])]
    // public function markRecurringEventImportant(...) { ... }
}
