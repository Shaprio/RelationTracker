<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use OpenApi\Attributes as OA;
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

        $events = $entityManager->getRepository(Event::class)->findBy(['userE' => $user]);
        $contacts = $entityManager->getRepository(Contact::class)->findBy(['userName' => $user]);

        if ($request->isMethod('POST')) {
            $eventId = $request->request->get('event_id');
            $title = $request->request->get('title');
            $description = $request->request->get('description');
            $dateTime = $request->request->get('date'); // Obsługa różnych formatów daty
            $selectedContacts = $request->request->all('contacts') ?? [];

            // Wywołanie metody parseDate(), aby obsłużyć różne formaty daty
            $dateObject = $this->parseDate($dateTime);
            if (!$dateObject) {
                return new JsonResponse(['error' => "Invalid date format. Received: '$dateTime'"], Response::HTTP_BAD_REQUEST);
            }

            if ($eventId) {
                $event = $entityManager->getRepository(Event::class)->find($eventId);
                if ($event && $event->getUserE() === $user) {
                    $event->setTitle($title)
                        ->setDescription($description)
                        ->setDate($dateObject)
                        ->setUpdateAt(new \DateTime());

                    foreach ($event->getContact() as $eventContact) {
                        $entityManager->remove($eventContact);
                    }

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
                $event = new Event();
                $event->setUserE($user)
                    ->setTitle($title)
                    ->setDescription($description)
                    ->setDate($dateObject)
                    ->setCreatedAt(new \DateTimeImmutable())
                    ->setUpdateAt(new \DateTime());

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
    private function parseDate(?string $dateString): ?\DateTime
    {
        if (!$dateString) {
            return null;
        }

        // Obsługa formatu "Y-m-d H:i:s"
        $dateObject = \DateTime::createFromFormat('Y-m-d H:i:s', $dateString);
        if ($dateObject) {
            return $dateObject;
        }

        // Obsługa formatu ISO 8601 "Y-m-d\TH:i"
        $dateObject = \DateTime::createFromFormat('Y-m-d\TH:i', $dateString);
        if ($dateObject) {
            return $dateObject;
        }

        // Obsługa standardowego formatu PHP DateTime
        try {
            return new \DateTime($dateString);
        } catch (\Exception $e) {
            return null;
        }
    }

    #[Route('/meetings/{id}/important', name: 'meeting_toggle_important', methods: ['POST'])]
    public function toggleMeetingImportant(Event $event, EntityManagerInterface $entityManager): JsonResponse
    {
        $event->setIsImportant(!$event->getIsImportant());
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'isImportant' => $event->getIsImportant()]);
    }

    #[Route('/meetings/{id}/delete', name: 'meeting_delete', methods: ['DELETE'])]
    public function deleteMeeting(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $event = $entityManager->getRepository(Event::class)->find($id);
        if (!$event) {
            return new JsonResponse(['error' => 'Meeting not found'], Response::HTTP_NOT_FOUND);
        }

        $entityManager->remove($event);
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'message' => 'Meeting deleted successfully.']);
    }


    #[Route('/api/meetings', name: 'api_meetings', methods: ['GET'])]
    #[OA\Get(
        path: '/api/meetings',
        summary: 'Get user meetings',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of meetings',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Event')
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function apiMeetings(EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $events = $entityManager->getRepository(Event::class)->findBy(['userE' => $user]);
        $data = [];

        foreach ($events as $event) {
            $data[] = [
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'description' => $event->getDescription(),
                'date' => $event->getDate()->format('Y-m-d H:i:s'),
                'contacts' => array_map(fn($contact) => [
                    'id' => $contact->getId(),
                    'name' => $contact->getName(),
                ], $event->getContact()->toArray()),
            ];
        }

        return $this->json($data);
    }

    #[Route('/api/meetings/create', name: 'api_meeting_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/meetings/create',
        summary: 'Create a new meeting',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['title', 'description', 'date'],
                properties: [
                    new OA\Property(property: 'title', type: 'string', example: 'Project Meeting'),
                    new OA\Property(property: 'description', type: 'string', example: 'Discuss project updates'),
                    new OA\Property(property: 'date', type: 'string', format: 'date-time', example: '2024-02-15 14:00:00'),
                    new OA\Property(property: 'contacts', type: 'array', items: new OA\Items(type: 'integer'))
                ]
            )
        ),
        responses: [
            new OA\Response(response: 201, description: 'Meeting created'),
            new OA\Response(response: 400, description: 'Validation error'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function apiCreateMeeting(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['title'], $data['description'], $data['date'])) {
            return $this->json(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        $event = new Event();
        $event->setUserE($user);
        $event->setTitle($data['title']);
        $event->setDescription($data['description']);
        $event->setDate(new \DateTime($data['date']));
        $event->setCreatedAt(new \DateTimeImmutable());
        $event->setUpdateAt(new \DateTime());

        $entityManager->persist($event);

        if (!empty($data['contacts'])) {
            foreach ($data['contacts'] as $contactId) {
                $contact = $entityManager->getRepository(Contact::class)->find($contactId);
                if ($contact) {
                    $eventContact = new EventContact();
                    $eventContact->setEvent($event);
                    $eventContact->setContact($contact);
                    $entityManager->persist($eventContact);
                }
            }
        }

        $entityManager->flush();
        return $this->json(['message' => 'Meeting created'], Response::HTTP_CREATED);
    }
    #[Route('/api/meetings/{id}/important', name: 'api_meeting_toggle_important', methods: ['PATCH'])]
    #[OA\Patch(
        path: '/api/meetings/{id}/important',
        summary: 'Toggle important status for a meeting',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Meeting importance toggled'),
            new OA\Response(response: 404, description: 'Meeting not found')
        ]
    )]
    public function apiToggleMeetingImportant(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $event = $entityManager->getRepository(Event::class)->find($id);
        if (!$event) {
            return new JsonResponse(['error' => 'Meeting not found'], 404);
        }

        $event->setIsImportant(!$event->getIsImportant());
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'isImportant' => $event->getIsImportant()]);
    }

}
