<?php

namespace App\Controller;

use Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use OpenApi\Attributes as OA;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;


class SecurityController extends AbstractController
{
    // **API Login**
    #[Route('/api/login', name: 'api_login', methods: ['POST'])]
    #[OA\Post(
        path: '/api/login',
        description: 'Logs in a user with email and password, returns JWT token.',
        summary: 'User Login (API)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['email', 'password'],
                properties: [
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'user@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'securepassword'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successfully logged in',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'token', type: 'string', example: 'JWT_TOKEN'),
                    ]
                )
            ),
            new OA\Response(
                response: 401,
                description: 'Invalid credentials',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'string', example: 'Invalid credentials.'),
                    ]
                )
            )
        ]
    )]
    public function apiLogin(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        JWTTokenManagerInterface $JWTManager
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$password) {
            return $this->json(['error' => 'Email and password are required.'], 400);
        }

        $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if (!$user || !$passwordHasher->isPasswordValid($user, $password)) {
            return $this->json(['error' => 'Invalid credentials.'], 401);
        }

        // Generate JWT token
        $token = $JWTManager->create($user);

        return $this->json(['token' => $token]);
    }


    #[Route('/forgot-password', name: 'forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(
        Request $request,
        EntityManagerInterface $entityManager,
        MailerInterface $mailer,
        UrlGeneratorInterface $urlGenerator // Dodajemy poprawnie URL Generator
    ): Response {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');

            $user = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

            if ($user) {
                // Generowanie tokena resetującego
                $resetToken = bin2hex(random_bytes(32));
                $user->setResetToken($resetToken);
                $entityManager->flush();

                // Generowanie pełnego URL do resetowania hasła
                $resetUrl = $urlGenerator->generate('reset_password', ['token' => $resetToken], UrlGeneratorInterface::ABSOLUTE_URL);

                // Logowanie adresu URL (opcjonalnie - do sprawdzenia, czy link jest poprawny)
                dump($resetUrl);

                // Tworzenie i wysyłanie wiadomości e-mail
                $emailMessage = (new Email())
                    ->from('no-reply@yourapp.com')
                    ->to($user->getEmail())
                    ->subject('Reset Your Password')
                    ->html("<p>Click <a href='$resetUrl'>here</a> to reset your password.</p>");

                $mailer->send($emailMessage);

                return $this->render('reset_email_sent.html.twig');
            }

            $this->addFlash('error', 'Email not found.');
        }

        return $this->render('forgot_password.html.twig');
    }


    #[Route('/reset-password/{token}', name: 'reset_password', methods: ['GET', 'POST'])]
    public function resetPassword(Request $request, string $token, EntityManagerInterface $entityManager, userPasswordHasherInterface $passwordEncoder): Response
    {
        $user = $entityManager->getRepository(User::class)->findOneBy(['resetToken' => $token]);

        if (!$user) {
            throw $this->createNotFoundException('Invalid or expired reset token.');
        }

        if ($request->isMethod('POST')) {
            $newPassword = $request->request->get('password');
            $user->setPassword($passwordEncoder->hashPassword($user, $newPassword));
            $user->setResetToken(null); // Usunięcie tokena resetującego po zmianie hasła
            $entityManager->flush();

            return $this->redirectToRoute('login');
        }

        return $this->render('reset_password.html.twig', ['token' => $token]);
    }



    #[Route('/api/register', name: 'api_register', methods: ['POST'])]
    #[OA\Post(
        path: '/api/register',
        description: 'Registers a new user with name, email, and password.',
        summary: 'User Registration (API)',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                required: ['name', 'email', 'password'],
                properties: [
                    new OA\Property(property: 'name', type: 'string', example: 'Jan Kowalski'),
                    new OA\Property(property: 'email', type: 'string', format: 'email', example: 'jan.kowalski@example.com'),
                    new OA\Property(property: 'password', type: 'string', example: 'securepassword'),
                ]
            )
        ),
        responses: [
            new OA\Response(
                response: 201,
                description: 'User successfully registered',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Registration successful!')
                    ]
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Validation error',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'error', type: 'array',
                            items: new OA\Items(type: 'string', example: 'Email address is already registered.')
                        )
                    ]
                )
            )
        ]
    )]
    public function apiRegister(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        $email = $data['email'] ?? null;
        $name = $data['name'] ?? null;
        $password = $data['password'] ?? null;

        if (!$email || !$name || !$password) {
            return $this->json(['error' => 'Name, email, and password are required.'], 400);
        }

        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($existingUser) {
            return $this->json(['error' => 'Email address is already registered.'], 400);
        }

        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_USER']);

        // Validate User entity
        $errors = $validator->validate($user);

        if (count($errors) > 0) {
            $errorMessages = [];
            foreach ($errors as $error) {
                $errorMessages[] = $error->getMessage();
            }
            return $this->json(['error' => $errorMessages], 400);
        }

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->json(['message' => 'Registration successful!'], 201);
    }

    // **API Logout**
    #[Route('/api/logout', name: 'api_logout', methods: ['POST'])]
    #[OA\Post(
        path: '/api/logout',
        description: 'Logs out the authenticated user by invalidating JWT token.',
        summary: 'User Logout (API)',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successfully logged out',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'message', type: 'string', example: 'Logged out successfully.')
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
    public function apiLogout(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        // Implement token invalidation logic here (e.g., add token to blacklist)
        // Currently, JWT is stateless and cannot be invalidated server-side without additional logic.

        return $this->json(['message' => 'Logged out successfully.'], 200);
    }

    // **Web Login**
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function loginWeb(AuthenticationUtils $authenticationUtils): Response
    {
        // Pobierz ostatni błąd uwierzytelniania, jeśli istnieje
        $error = $authenticationUtils->getLastAuthenticationError();

        // Pobierz ostatnio używaną nazwę użytkownika
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    // **Web Register**
    #[Route('/register', name: 'register', methods: ['GET'])]
    public function registerWeb(): Response
    {
        return $this->render('register.html.twig');
    }

    #[Route('/register/submit', name: 'register_submit', methods: ['POST'])]
    public function registerSubmitWeb(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        ValidatorInterface $validator
    ): Response
    {
        $email = $request->request->get('email');
        $name = $request->request->get('name');
        $password = $request->request->get('password');

        // Sprawdzenie, czy użytkownik z danym email już istnieje
        $existingUser = $entityManager->getRepository(User::class)->findOneBy(['email' => $email]);

        if ($existingUser) {
            $this->addFlash('error', 'Email address is already registered.');

            return $this->redirectToRoute('register');
        }

        // Tworzenie nowego użytkownika
        $user = new User();
        $user->setEmail($email);
        $user->setName($name);
        $user->setPassword($passwordHasher->hashPassword($user, $password));
        $user->setRoles(['ROLE_USER']);

        // Walidacja encji User
        $errors = $validator->validate($user);

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error->getMessage());
            }

            return $this->redirectToRoute('register');
        }

        // Zapis użytkownika do bazy danych
        $entityManager->persist($user);
        $entityManager->flush();

        $this->addFlash('success', 'Registration successful! You can now log in.');

        return $this->redirectToRoute('login');
    }

    // **Web Logout**
    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logoutWeb(): void
    {
        // Ten kod nigdy nie zostanie wywołany, ponieważ wylogowanie jest przechwytywane przez firewall.
        throw new Exception('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }
}
