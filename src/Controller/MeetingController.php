<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\User;
use App\Entity\Event;
use App\Entity\EventContact;
use App\Entity\Contact;

class MeetingController extends AbstractController
{
    #[Route('/meetings', name: 'meetings', methods: ['GET', 'POST'])]
    public function meetings(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        // Pobieranie wydarzeń użytkownika
        $events = $entityManager->getRepository(Event::class)->findBy(['userE' => $user]);
        $contacts = $entityManager->getRepository(Contact::class)->findBy(['userName' => $user]);

        if ($request->isMethod('POST')) {
            $eventId = $request->request->get('event_id');
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $date = $request->request->get('date');
            $selectedContacts = $request->request->all('contacts') ?? [];

            if ($eventId) {
                // Edycja istniejącego wydarzenia
                $event = $entityManager->getRepository(Event::class)->find($eventId);
                if ($event && $event->getUserE() === $user) {
                    $event->setTitle($title);
                    $event->setDescription($description);
                    $event->setDate(new \DateTime($date));
                    $event->setUpdateAt(new \DateTime());

                    // Usuwamy poprzednie przypisania (EventContact)
                    foreach ($event->getContact() as $eventContact) {
                        $entityManager->remove($eventContact);
                    }
                    // Dodajemy nowe
                    foreach ($selectedContacts as $contactId) {
                        $contact = $entityManager->getRepository(Contact::class)->find($contactId);
                        if ($contact) {
                            $eventContact = new EventContact();
                            $eventContact->setEvent($event);
                            $eventContact->setContact($contact);
                            $entityManager->persist($eventContact);
                        }
                    }
                    $entityManager->flush();
                }
            } else {
                // Dodawanie nowego wydarzenia
                $event = new Event();
                $event->setUserE($user);
                $event->setTitle($title);
                $event->setDescription($description);
                $event->setDate(new \DateTime($date));
                $event->setCreatedAt(new \DateTimeImmutable());
                $event->setUpdateAt(new \DateTime());

                $entityManager->persist($event);

                foreach ($selectedContacts as $contactId) {
                    $contact = $entityManager->getRepository(Contact::class)->find($contactId);
                    if ($contact) {
                        $eventContact = new EventContact();
                        $eventContact->setEvent($event);
                        $eventContact->setContact($contact);
                        $entityManager->persist($eventContact);
                    }
                }
                $entityManager->flush();
            }

            return $this->redirectToRoute('meetings');
        }

        return $this->render('meetings.html.twig', [
            'events' => $events,
            'contacts' => $contacts,
        ]);
    }

    #[Route('/meetings/{id}/important', name: 'meeting_toggle_important', methods: ['POST'])]
    public function toggleMeetingImportant(Event $event, EntityManagerInterface $entityManager): JsonResponse
    {
        $event->setIsImportant(!$event->getIsImportant());
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'isImportant' => $event->getIsImportant()]);
    }
}
