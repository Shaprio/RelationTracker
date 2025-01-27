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
use App\Entity\User;
use App\Entity\Contact;


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
    #[OA\Get(
        path: '/mainPage',
        summary: 'Render main page',
        responses: [
            new OA\Response(response: 200, description: 'Main page rendered')
        ]
    )]
    public function mainPage(): Response
    {
        return $this->render('mainPage.html.twig');
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

        $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->redirectToRoute('login');
    }

    #[Route('/meetings', name: 'meetings', methods: ['GET'])]
    #[OA\Get(
        path: '/meetings',
        summary: 'Render meetings page',
        responses: [
            new OA\Response(response: 200, description: 'Meetings page rendered')
        ]
    )]
    public function meetings(): Response
    {
        return $this->render('meetings.html.twig');
    }

    #[Route('/friends', name: 'friends', methods: ['GET', 'POST'])]
    public function friends(Request $request, EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();

        if (!$user) {
            return $this->redirectToRoute('login');
        }

        // Pobieranie kontaktów użytkownika
        $contacts = $entityManager->getRepository(Contact::class)->findBy(['userName' => $user]);

        if ($request->isMethod('POST')) {
            $contactId = $request->request->get('contact_id');
            $name = $request->request->get('name');
            $email = $request->request->get('email');
            $phone = $request->request->get('phone');
            $birthday = $request->request->get('birthday'); // Pobranie daty urodzin
            $note = $request->request->get('note');

            if ($contactId) {
                // Edycja istniejącego kontaktu
                $contact = $entityManager->getRepository(Contact::class)->find($contactId);
                if ($contact && $contact->getUserName() === $user) {
                    $contact->setName($name);
                    $contact->setEmailC($email);
                    $contact->setPhone($phone);
                    $contact->setBirthday($birthday ? new \DateTime($birthday) : null); // Konwersja daty na obiekt DateTime
                    $contact->setNote($note);
                    $contact->setUpdateAt(new \DateTime());
                    $entityManager->flush();
                }
            } else {
                // Dodawanie nowego kontaktu
                $contact = new Contact();
                $contact->setUserName($user);
                $contact->setName($name);
                $contact->setEmailC($email);
                $contact->setPhone($phone);
                $contact->setBirthday($birthday ? new \DateTime($birthday) : null); // Konwersja daty na obiekt DateTime
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



    #[Route('/notifications', name: 'notifications', methods: ['GET'])]
    #[OA\Get(
        path: '/notifications',
        summary: 'Render notifications page',
        responses: [
            new OA\Response(response: 200, description: 'Notifications page rendered')
        ]
    )]
    public function notifications(): Response
    {
        return $this->render('notifications.html.twig');
    }

    #[Route('/settings', name: 'settings', methods: ['GET'])]
    #[OA\Get(
        path: '/settings',
        summary: 'Render settings page',
        responses: [
            new OA\Response(response: 200, description: 'Settings page rendered')
        ]
    )]
    public function settings(): Response
    {
        return $this->render('settings.html.twig');
    }
}



