<?php

namespace App\Controller;

use App\Entity\RecurringEvent;
use DateTime;
use DateTimeImmutable;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Entity\Contact;
use App\Entity\Interaction;
use OpenApi\Attributes as OA;

class ContactController extends AbstractController
{
    #[Route('/friends', name: 'friends', methods: ['GET', 'POST'])]
    public function friends(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $contacts = $entityManager->getRepository(Contact::class)->findBy(['userName' => $user]);

        if ($request->isMethod('POST')) {
            $contactId = $request->request->get('contact_id');
            $name = $request->request->get('name');
            $email = $request->request->get('email');
            $phone = $request->request->get('phone');
            $birthday = $request->request->get('birthday');
            $note = $request->request->get('note');

            if ($contactId) {
                $contact = $entityManager->getRepository(Contact::class)->find($contactId);
                if ($contact && $contact->getUserName() === $user) {
                    $contact->setName($name);
                    $contact->setEmailC($email);
                    $contact->setPhone($phone);
                    $contact->setBirthday($birthday ? new DateTime($birthday) : null);
                    $contact->setNote($note);
                    $contact->setUpdateAt(new DateTime());

                    if ($birthday) {
                        $this->updateOrCreateBirthdayEvent($contact, $entityManager);
                    }

                    $entityManager->flush();
                }
            } else {
                $contact = new Contact();
                $contact->setUserName($user);
                $contact->setName($name);
                $contact->setEmailC($email);
                $contact->setPhone($phone);
                $contact->setBirthday($birthday ? new DateTime($birthday) : null);
                $contact->setNote($note);
                $contact->setCreatedAt(new DateTimeImmutable());
                $contact->setUpdateAt(new DateTime());

                $entityManager->persist($contact);
                $entityManager->flush();

                if ($birthday) {
                    $this->updateOrCreateBirthdayEvent($contact, $entityManager);
                }
            }

            return $this->redirectToRoute('friends');
        }

        return $this->render('friends.html.twig', [
            'contacts' => $contacts,
        ]);
    }
    private function updateOrCreateBirthdayEvent(Contact $contact, EntityManagerInterface $entityManager): void
    {
        $birthday = $contact->getBirthday();
        if (!$birthday) {
            return;
        }

        $user = $contact->getUserName();

        $existingEvent = $entityManager->getRepository(RecurringEvent::class)->findOneBy([
            'owner' => $user,
            'title' => 'Birthday',
            'startDate' => $birthday,
        ]);

        if (!$existingEvent) {
            $birthdayEvent = new RecurringEvent();
            $birthdayEvent->setTitle('Birthday');
            $birthdayEvent->setDescription($contact->getName() . ' - Birthday');
            $birthdayEvent->setStartDate($birthday);
            $birthdayEvent->setRecurrencePattern('yearly');
            $birthdayEvent->setCreatedAt(new DateTimeImmutable());
            $birthdayEvent->setUpdatedAt(new DateTime());
            $birthdayEvent->setOwner($user);
            $birthdayEvent->addContact($contact);

            $entityManager->persist($birthdayEvent);
            $entityManager->flush();
        }
    }

    #[Route('/friends/interact', name: 'log_interaction', methods: ['POST'])]
    public function logInteraction(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $contactId = $request->request->get('contact_id');
        $initiatedBy = $request->request->get('initiatedBy');

        $contact = $entityManager->getRepository(Contact::class)->find($contactId);

        if ($contact && $contact->getUserName() === $user) {
            $interaction = new Interaction();
            $interactionDate = new DateTimeImmutable();
            $interaction->setContact($contact);
            $interaction->setInitiatedBy($initiatedBy);
            $interaction->setInteractionDate($interactionDate);

            $contact->setLastInteraction($interactionDate);

            $entityManager->persist($interaction);
            $entityManager->flush();

            $this->addFlash('success', 'Interaction logged successfully!');
        } else {
            $this->addFlash('error', 'Contact not found or unauthorized!');
        }

        return $this->redirectToRoute('friends');
    }
    #[Route('/friends/{id}/details', name: 'contact_details', methods: ['GET'])]
    public function contactDetails(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $contact = $entityManager->getRepository(Contact::class)->find($id);
        if (!$contact || $contact->getUserName() !== $user) {
            return $this->json(['error' => 'Contact not found or unauthorized.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $contact->getId(),
            'name' => $contact->getName(),
            'emailC' => $contact->getEmailC(),
            'phone' => $contact->getPhone(),
            'address' => $contact->getAddress(),
            'birthday' => $contact->getBirthday() ? $contact->getBirthday()->format('Y-m-d') : null,
            'relationship' => $contact->getRelationship(),
            'note' => $contact->getNote(),
        ]);
    }
    #[Route('/friends/{id}/delete', name: 'delete_contact', methods: ['POST'])]
    public function deleteContact(int $id, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $contact = $entityManager->getRepository(Contact::class)->find($id);
        if ($contact && $contact->getUserName() === $user) {
            $entityManager->remove($contact);
            $entityManager->flush();
            $this->addFlash('success', 'Contact deleted successfully.');
        } else {
            $this->addFlash('error', 'Contact not found or unauthorized.');
        }

        return $this->redirectToRoute('friends');
    }
    #[Route('/api/friends', name: 'api_friends', methods: ['GET', 'POST'])]
    #[OA\Get(
        path: '/api/friends',
        description: 'Retrieve a list of user\'s contacts.',
        summary: 'Get Friends List',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'List of contacts',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: '#/components/schemas/Contact')
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Unauthorized.')
                    ]
                )
            )
        ]
    )]
    #[OA\Post(
        path: '/api/friends',
        description: 'Create a new contact.',
        summary: 'Create a New Friend',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'phone'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Jan Kowalski'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jan.kowalski@example.com'),
                    new OA\Property(property: 'phone', type: 'string', example: '+48123456789'),
                    new OA\Property(property: 'birthday', type: 'string', format: 'date', example: '1990-01-01'),
                    new OA\Property(property: 'note', type: 'string', example: 'Friend from school'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'Contact created successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Contact created successfully.')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid input data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'array', items: new OA\Items(type: 'string'))
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Unauthorized.')
                    ]
                )
            )
        ]
    )]
    public function apiFriends(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        if ($request->isMethod('POST')) {
            $data = json_decode($request->getContent(), true);
            $name = $data['name'] ?? null;
            $email = $data['email'] ?? null;
            $phone = $data['phone'] ?? null;
            $birthday = $data['birthday'] ?? null;
            $note = $data['note'] ?? null;

            if (!$name || !$email || !$phone) {
                return $this->json(['error' => 'Name, email, and phone are required.'], Response::HTTP_BAD_REQUEST);
            }

            // Sprawdzenie, czy kontakt z danym email już istnieje
            $existingContact = $entityManager->getRepository(Contact::class)->findOneBy(['emailC' => $email, 'userName' => $user]);
            if ($existingContact) {
                return $this->json(['error' => 'Email address is already registered.'], Response::HTTP_BAD_REQUEST);
            }

            // Tworzenie nowego kontaktu
            $contact = new Contact();
            $contact->setUserName($user);
            $contact->setName($name);
            $contact->setEmailC($email);
            $contact->setPhone($phone);
            $contact->setBirthday($birthday ? new DateTime($birthday) : null);
            $contact->setNote($note);
            $contact->setCreatedAt(new DateTimeImmutable());
            $contact->setUpdateAt(new DateTime());

            // Walidacja encji Contact
            $errors = $this->get('validator')->validate($contact);
            if (count($errors) > 0) {
                $errorMessages = [];
                foreach ($errors as $error) {
                    $errorMessages[] = $error->getMessage();
                }
                return $this->json(['error' => $errorMessages], Response::HTTP_BAD_REQUEST);
            }

            $entityManager->persist($contact);
            $entityManager->flush();

            return $this->json(['message' => 'Contact created successfully.'], Response::HTTP_CREATED);
        }

        // Jeśli GET, zwróć listę kontaktów
        $contacts = $entityManager->getRepository(Contact::class)->findBy(['userName' => $user]);

        $data = [];
        foreach ($contacts as $contact) {
            $data[] = [
                'id' => $contact->getId(),
                'name' => $contact->getName(),
                'emailC' => $contact->getEmailC(),
                'phone' => $contact->getPhone(),
                'birthday' => $contact->getBirthday() ? $contact->getBirthday()->format('Y-m-d') : null,
                'note' => $contact->getNote(),
            ];
        }

        return $this->json($data, Response::HTTP_OK);
    }
    #[Route('/api/friends/interact', name: 'api_log_interaction', methods: ['POST'])]
    #[OA\Post(
        path: '/api/friends/interact',
        description: 'Logs an interaction with a contact.',
        summary: 'Log Interaction',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['contact_id', 'initiatedBy'],
                properties: [
                    new OA\Property(property: 'contact_id', type: 'integer', example: 1),
                    new OA\Property(property: 'initiatedBy', type: 'string', example: 'self') // 'self' lub 'friend'
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Interaction logged successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Interaction logged successfully.')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid input data',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Invalid input data.')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Unauthorized.')
                    ]
                )
            )
        ]
    )]
    public function apiLogInteraction(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);
        $contactId = $data['contact_id'] ?? null;
        $initiatedBy = $data['initiatedBy'] ?? null;

        if (!$contactId || !$initiatedBy) {
            return $this->json(['error' => 'contact_id and initiatedBy are required.'], Response::HTTP_BAD_REQUEST);
        }

        $contact = $entityManager->getRepository(Contact::class)->find($contactId);

        if ($contact && $contact->getUserName() === $user) {
            $interaction = new Interaction();
            $interactionDate = new DateTimeImmutable();
            $interaction->setContact($contact);
            $interaction->setInitiatedBy($initiatedBy);
            $interaction->setInteractionDate($interactionDate);

            // Aktualizacja pola lastInteraction
            $contact->setLastInteraction($interactionDate);

            $entityManager->persist($interaction);
            $entityManager->flush();

            return $this->json(['message' => 'Interaction logged successfully.'], Response::HTTP_OK);
        } else {
            return $this->json(['error' => 'Contact not found or unauthorized.'], Response::HTTP_NOT_FOUND);
        }
    }
    #[Route('/api/friends/{id}/details', name: 'api_contact_details', methods: ['GET'])]
    #[OA\Get(
        path: '/api/friends/{id}/details',
        description: 'Retrieve detailed information about a specific contact.',
        summary: 'Get Contact Details',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the contact',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contact details retrieved successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'id', type: 'integer', example: 1),
                        new OA\Property(property: 'name', type: 'string', example: 'Jan Kowalski'),
                        new OA\Property(property: 'emailC', type: 'string', format: 'email', example: 'jan.kowalski@example.com'),
                        new OA\Property(property: 'phone', type: 'string', example: '+48123456789'),
                        new OA\Property(property: 'address', type: 'string', example: '123 Main St'),
                        new OA\Property(property: 'birthday', type: 'string', format: 'date', example: '1990-01-01'),
                        new OA\Property(property: 'relationship', type: 'string', example: 'Friend'),
                        new OA\Property(property: 'note', type: 'string', example: 'Met at school'),
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Contact not found or unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Contact not found or unauthorized.')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Unauthorized.')
                    ]
                )
            )
        ]
    )]
    public function apiContactDetails(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $contact = $entityManager->getRepository(Contact::class)->find($id);
        if (!$contact || $contact->getUserName() !== $user) {
            return $this->json(['error' => 'Contact not found or unauthorized.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json([
            'id' => $contact->getId(),
            'name' => $contact->getName(),
            'emailC' => $contact->getEmailC(),
            'phone' => $contact->getPhone(),
            'address' => $contact->getAddress(),
            'birthday' => $contact->getBirthday() ? $contact->getBirthday()->format('Y-m-d') : null,
            'relationship' => $contact->getRelationship(),
            'note' => $contact->getNote(),
        ], Response::HTTP_OK);
    }
    #[Route('/api/friends/{id}/delete', name: 'api_delete_contact', methods: ['POST'])]
    #[OA\Post(
        path: '/api/friends/{id}/delete',
        description: 'Deletes a specific contact.',
        summary: 'Delete Contact',
        security: [['Bearer' => []]],
        parameters: [
            new OA\Parameter(
                name: 'id',
                description: 'ID of the contact to delete',
                in: 'path',
                required: true,
                schema: new OA\Schema(type: 'integer')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Contact deleted successfully',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Contact deleted successfully.')
                    ]
                )
            ),
            new OA\Response(
                response: 404,
                description: 'Contact not found or unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Contact not found or unauthorized.')
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Unauthorized',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Unauthorized.')
                    ]
                )
            )
        ]
    )]
    public function apiDeleteContact(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized.'], Response::HTTP_UNAUTHORIZED);
        }

        $contact = $entityManager->getRepository(Contact::class)->find($id);
        if ($contact && $contact->getUserName() === $user) {
            $entityManager->remove($contact);
            $entityManager->flush();
            return $this->json(['message' => 'Contact deleted successfully.'], Response::HTTP_OK);
        } else {
            return $this->json(['error' => 'Contact not found or unauthorized.'], Response::HTTP_NOT_FOUND);
        }
    }
}
