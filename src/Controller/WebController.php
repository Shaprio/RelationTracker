<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\User;
use App\Entity\Contact;
use App\Entity\Event;
use App\Entity\EventContact;
use App\Entity\RecurringEvent;
use App\Entity\RecurringEventContact;
use App\Entity\Interaction;
use App\Entity\Setting;



class WebController extends AbstractController
{
    #[Route('/api/admin', name: 'admin_dashboard', methods: ['GET'])]
    #[OA\Get(
        path: '/api/admin',
        summary: 'Access admin dashboard',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(response: 200, description: 'Admin content'),
            new OA\Response(response: 403, description: 'Access denied')
        ]
    )]
    public function admin(): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');
        return new Response('Admin content');
    }

    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    #[OA\Get(
        path: '/login',
        summary: 'Render login page',
        responses: [
            new OA\Response(response: 200, description: 'Login page rendered')
        ]
    )]
    public function login(): Response
    {
        return $this->render('login.html.twig');
    }

    #[Route('/api/login', name: 'api_login', methods: ['POST', 'OPTIONS'])]
    #[OA\Post(
        path: '/api/login',
        summary: 'Authenticate user and return JWT token',
        tags: ['Authentication'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['username', 'password'],
                properties: [
                    new OA\Property(property: 'username', type: 'string', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'password123'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'JWT token returned',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9...')
                    ]
                )
            ),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 401, description: 'Invalid credentials'),
        ]
    )]
    public function apiLogin(Request $request, JWTTokenManagerInterface $JWTManager, EntityManagerInterface $entityManager): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['username'], $data['password'])) {
            return $this->json(['error' => 'Username and password are required'], Response::HTTP_BAD_REQUEST);
        }

        $user = $entityManager->getRepository(User::class)
            ->findOneBy(['email' => $data['username']]);

        if (!$user || !password_verify($data['password'], $user->getPassword())) {
            return $this->json(['error' => 'Invalid credentials'], Response::HTTP_UNAUTHORIZED);
        }

        $token = $JWTManager->create($user);

        return $this->json(['token' => $token]);
    }

    #[Route('/logout', name: 'logout', methods: ['GET'])]
    #[OA\Get(
        path: '/logout',
        summary: 'Logout the user',
        responses: [
            new OA\Response(response: 200, description: 'User logged out')
        ]
    )]
    public function logout(): void
    {
        throw new \Exception('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

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
        //    Załóżmy, że w ContactRepository (lub InteractionRepository)
        //    masz metodę findStaleContacts(...).
        $staleContacts = $entityManager
            ->getRepository(Contact::class)
            ->findStaleContacts($user);

        // 5. Dzisiejsze wydarzenia (Meetings + RecurringEvents) - można scalić w jedną tablicę lub oddzielnie
        $todayEvents = [];
        // a) meetingi
        $todayEventsMeetings = $entityManager
            ->getRepository(Event::class)
            ->findTodayForUser($user);
        // b) recurring
        $todayEventsRecurring = $entityManager
            ->getRepository(RecurringEvent::class)
            ->findTodayForUser($user);

        // scalamy
        $todayEvents = array_merge($todayEventsMeetings, $todayEventsRecurring);

        return $this->render('mainPage.html.twig', [
            'eventsThisWeek'      => $eventsThisWeek,
            'recurringThisWeek'   => $recurringThisWeek,
            'birthdayEvents'      => $birthdayEvents,
            'staleContacts'       => $staleContacts,
            'todayEvents'         => $todayEvents,
        ]);
    }

    #[Route('/register', name: 'register', methods: ['GET'])]
    #[OA\Get(
        path: '/register',
        summary: 'Render registration form',
        responses: [
            new OA\Response(response: 200, description: 'Registration form rendered')
        ]
    )]
    public function register(): Response
    {
        return $this->render('register.html.twig');
    }

    #[Route('/register/submit', name: 'register_submit', methods: ['POST'])]
    #[OA\Post(
        path: '/register/submit',
        summary: 'Register a new user',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password', 'name'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'password123'),
                    new OA\Property(property: 'name', type: 'string', example: 'John Doe')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 302, description: 'User registered and redirected')
        ]
    )]
    public function registerSubmit(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $email = $request->request->get('email');
        $plainPassword = $request->request->get('password');
        $name = $request->request->get('name');

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);

        $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->redirectToRoute('login');
    }

    #[Route('/meetings', name: 'meetings', methods: ['GET', 'POST'])]
    public function meetings(
        Request $request,
        EntityManagerInterface $entityManager
    ): Response {
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

                    // Aktualizacja kontaktów
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
                // Update existing contact
                $contact = $entityManager->getRepository(Contact::class)->find($contactId);
                if ($contact && $contact->getUserName() === $user) {
                    $contact->setName($name);
                    $contact->setEmailC($email);
                    $contact->setPhone($phone);
                    $contact->setBirthday($birthday ? new \DateTime($birthday) : null);
                    $contact->setNote($note);
                    $contact->setUpdateAt(new \DateTime());
                    $entityManager->flush();
                }
            } else {
                // Create new contact
                $contact = new Contact();
                $contact->setUserName($user);
                $contact->setName($name);
                $contact->setEmailC($email);
                $contact->setPhone($phone);
                $contact->setBirthday($birthday ? new \DateTime($birthday) : null);
                $contact->setNote($note);
                $contact->setCreatedAt(new \DateTimeImmutable());
                $contact->setUpdateAt(new \DateTime());
                $entityManager->persist($contact);
                $entityManager->flush();
            }

            return $this->redirectToRoute('friends');
        }

        return $this->render('friends.html.twig', [
            'contacts' => $contacts,
        ]);
    }

    #[Route('/friends/interact', name: 'log_interaction', methods: ['POST'])]
    public function logInteraction(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $contactId   = $request->request->get('contact_id');
        $initiatedBy = $request->request->get('initiatedBy'); // 'self' or 'friend'

        $contact = $entityManager->getRepository(Contact::class)->find($contactId);

        if ($contact && $contact->getUserName() === $user) {
            // 1. Tworzymy nową Interakcję
            $interaction = new Interaction();
            $interactionDate = new \DateTimeImmutable();
            $interaction->setContact($contact);
            $interaction->setInitiatedBy($initiatedBy);
            $interaction->setInteractionDate($interactionDate);

            // 2. Uzupełniamy pole lastInteraction w Contact
            //    (np. data z Interaction)
            $contact->setLastInteraction($interactionDate);

            // 3. Zapisujemy w bazie
            $entityManager->persist($interaction);
            // $entityManager->persist($contact); // nie zawsze konieczne, bo cascade lub flush
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

    #[Route('/notifications', name: 'notifications')]
    public function notifications(EntityManagerInterface $entityManager, Security $security): Response
    {
        $user = $security->getUser();

        if (!$user) {
            return $this->redirectToRoute('login'); // Redirect to login if user is not authenticated
        }

        // Fetch important meetings
        $importantMeetings = $entityManager->getRepository(Event::class)->findBy([
            'userE' => $user,
            'isImportant' => true,
        ]);

        // Convert meetings into an array with additional type info
        $meetings = array_map(function ($meeting) {
            return [
                'type' => 'Meeting',
                'title' => $meeting->getTitle(),
                'date' => $meeting->getDate(),
                'description' => $meeting->getDescription(),
            ];
        }, $importantMeetings);

        // Fetch important recurring events
        $importantRecurringEvents = $entityManager->getRepository(RecurringEvent::class)->findBy([
            'owner' => $user,
            'isImportant' => true,
        ]);

        // Convert recurring events into an array with additional type info
        $recurringEvents = array_map(function ($recurringEvent) {
            return [
                'type' => 'Recurring Event',
                'title' => $recurringEvent->getTitle(),
                'date' => $recurringEvent->getStartDate(),
                'description' => $recurringEvent->getDescription(),
            ];
        }, $importantRecurringEvents);

        // Merge and sort the events by date
        $allEvents = array_merge($meetings, $recurringEvents);

        usort($allEvents, function ($a, $b) {
            return $a['date'] <=> $b['date'];
        });

        return $this->render('notifications.html.twig', [
            'events' => $allEvents,
        ]);
    }


    #[Route('/settings', name: 'app_settings', methods: ['GET'])]
    public function settings(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        // Pobieramy Setting powiązany z user
        // Załóżmy, że w user masz $user->getSetting().
        // Jeśli jeszcze nie istnieje, tworzymy nowy:
        $setting = $user->getSetting();
        if (!$setting) {
            $setting = new Setting();
            $setting->setUser($user);
            // ewentualnie domyślne preferencje
            $em->persist($setting);
            $em->flush();
            $user->setSetting($setting);
        }

        return $this->render('settings.html.twig', [
            'user' => $user,
            'setting' => $setting,
        ]);
    }

    #[Route('/settings/update', name: 'app_settings_update', methods: ['POST'])]
    public function updateSettings(Request $request, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        // Podstawowe dane użytkownika
        $username = $request->request->get('username');
        $email    = $request->request->get('email');
        $password = $request->request->get('password');

        $user->setName($username);
        $user->setEmail($email);

        if ($password) {
            // tutaj ewentualnie hashowanie
            // $hashedPassword = ...
            // $user->setPassword($hashedPassword);
        }

        // Preferencje
        /** @var Setting $setting */
        $setting = $user->getSetting();
        if (!$setting) {
            // teoretycznie nie powinno się zdarzyć, bo tworzymy w GET, ale w razie W:
            $setting = new Setting();
            $setting->setUser($user);
            $em->persist($setting);
        }

        // Checkbox 'notifications' (zwraca 'on' lub null)
        $notifications = $request->request->get('notifications') === 'on';
        $setting->setNotifications($notifications);

        // Checkbox 'darkMode'
        $darkMode = $request->request->get('darkMode') === 'on';
        $setting->setDarkMode($darkMode);

        // select 'fontSize' => 'small', 'medium', 'big'
        $fontSize = $request->request->get('fontSize', 'medium');
        $setting->setFontSize($fontSize);

        $em->flush();

        $this->addFlash('success', 'Settings updated successfully.');

        return $this->redirectToRoute('app_settings');
    }

    #[Route('/recurring-events', name: 'recurring_events', methods: ['GET', 'POST'])]
    public function recurringEvents(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        // Pobierz wydarzenia powiązane z użytkownikiem
        $recurringEvents = $entityManager->getRepository(RecurringEvent::class)
            ->findBy(['owner' => $user]);

        // Pobierz kontakty użytkownika
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
                // Edytuj istniejące wydarzenie
                $event = $entityManager->getRepository(RecurringEvent::class)->find($eventId);
                $event->setTitle($title)
                    ->setDescription($description)
                    ->setRecurrencePattern($recurrencePattern)
                    ->setStartDate(new \DateTime($startDate))
                    ->setEndDate($endDate ? new \DateTime($endDate) : null)
                    ->setUpdatedAt(new \DateTime());

                // Usuń istniejące kontakty
                foreach ($event->getContacts() as $existingContact) {
                    $entityManager->remove($existingContact);
                }

                // Dodaj nowe kontakty
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
                // Dodaj nowe wydarzenie
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

                // Dodaj kontakty do wydarzenia
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

        // Return data including associated contacts if needed
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


    #[Route('/recurring-events/{id}/important', name: 'mark_recurring_event_important', methods: ['POST'])]
    public function markRecurringEventImportant(int $id, EntityManagerInterface $entityManager): JsonResponse
    {
        $event = $entityManager->getRepository(RecurringEvent::class)->find($id);

        if (!$event) {
            return new JsonResponse(['success' => false, 'message' => 'Event not found'], 404);
        }

        $event->setIsImportant(!$event->getIsImportant());
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'isImportant' => $event->getIsImportant()]);
    }

    #[Route('/meetings/{id}/important', name: 'meeting_toggle_important', methods: ['POST'])]
    public function toggleMeetingImportant(Event $event, EntityManagerInterface $entityManager): JsonResponse
    {
        $event->setIsImportant(!$event->getIsImportant()); // Toggle the isImportant flag
        $entityManager->flush();

        return new JsonResponse(['success' => true, 'isImportant' => $event->getIsImportant()]);
    }

    #[Route('/statistics', name: 'statistics', methods: ['GET'])]
    public function statistics(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        // Pobieranie liczby kontaktów
        $contactCount = $entityManager->getRepository(Contact::class)
            ->count(['userName' => $user]);

        // Pobieranie liczby wydarzeń (Meetings + Recurring)
        $eventCount = $entityManager->getRepository(Event::class)
            ->count(['userE' => $user]);

        $recurringEventCount = $entityManager->getRepository(RecurringEvent::class)
            ->count(['owner' => $user]);

        // Liczba interakcji użytkownika
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

        // TOP 5 kontaktów, które inicjują najwięcej interakcji z użytkownikiem
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
            'contactCount' => $contactCount,
            'eventCount' => $eventCount,
            'recurringEventCount' => $recurringEventCount,
            'interactionCount' => $interactionCount,
            'topContacts' => $topContacts,
            'initiatedByUser' => $initiatedByUser,
            'initiatedByContact' => $initiatedByContact,
        ]);
    }





}



