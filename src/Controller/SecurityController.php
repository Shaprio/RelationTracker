<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
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

    #[Route('/register', name: 'register', methods: ['GET'])]
    public function register(): Response
    {
        return $this->render('register.html.twig');
    }

    #[Route('/register/submit', name: 'register_submit', methods: ['POST'])]
    public function registerSubmit(
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

        // Ustawienie roli użytkownika
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

    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \Exception('This method will be intercepted by the firewall.');
    }
}
