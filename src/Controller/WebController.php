<?php

namespace App\Controller;

use OpenApi\Annotations as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

class WebController extends AbstractController
{
    #[Route('/api/admin', name: 'admin_dashboard')]
    public function admin(): Response
    {
        // Przykład kontrolowanego dostępu na podstawie roli
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        return new Response('Admin content');
    }

    #[Route('/login', name: 'login', methods: ['GET'])]
    public function login(): Response
    {
        // Po prostu renderujemy formularz logowania
        // Nie obsługujemy POST, bo robi to security "form_login"
        return $this->render('login.html.twig');
    }

    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): void
    {
        // Metoda może być pusta – mechanizm security przechwyci to żądanie
        throw new \Exception('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    #[Route('/mainPage', name: 'mainPage')]
    public function mainPage(): Response
    {
        // Strona główna (po zalogowaniu) – przykładowo
        return new Response('<h1>Welcome to the Main Page</h1>');
    }

    #[Route('/register', name: 'register', methods: ['GET'])]
    public function register(): Response
    {
        // Wyświetla formularz rejestracji (register.html.twig)
        return $this->render('register.html.twig');
    }


    #[Route('/register/submit', name: 'register_submit', methods: ['POST'])]
    public function registerSubmit(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        // 1. Pobierz dane z formularza
        $email = $request->request->get('email');
        $plainPassword = $request->request->get('password');
        $name = $request->request->get('name');

        // 2. Utwórz nowego użytkownika
        $user = new User();
        $user->setEmail($email);

        // 3. Haszuj hasło
        $hashedPassword = $passwordHasher->hashPassword($user, $plainPassword);
        $user->setPassword($hashedPassword);

        // 4. Zapisz w bazie
        $entityManager->persist($user);
        $entityManager->flush();

        // 5. Przekieruj do strony logowania (lub innej)
        return $this->redirectToRoute('login');
    }


    #[Route('/meetings', name: 'meetings')]
    public function meetings(): Response
    {
        return $this->render('meetings.html.twig');
    }

    #[Route('/friends', name: 'friends')]
    public function friends(): Response
    {
        return $this->render('friends.html.twig');
    }


    #[Route('/notifications', name: 'notifications')]
    public function notifications(): Response
    {
        return $this->render('notifications.html.twig');
    }


    #[Route('/settings', name: 'settings')]
    public function settings(): Response
    {
        return $this->render('settings.html.twig');
    }
}

