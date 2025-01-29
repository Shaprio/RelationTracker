<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;

class SecurityController extends AbstractController
{
    #[Route('/login', name: 'login', methods: ['GET', 'POST'])]
    public function login(): Response
    {
        return $this->render('login.html.twig');
    }

    #[Route('/register', name: 'register', methods: ['GET'])]
    public function register(): Response
    {
        return $this->render('register.html.twig');
    }

    #[Route('/register/submit', name: 'register_submit', methods: ['POST'])]
    public function registerSubmit(Request $request, UserPasswordHasherInterface $passwordHasher, EntityManagerInterface $entityManager): Response
    {
        $user = new User();
        $user->setEmail($request->request->get('email'));
        $user->setName($request->request->get('name'));
        $user->setPassword($passwordHasher->hashPassword($user, $request->request->get('password')));

        $entityManager->persist($user);
        $entityManager->flush();

        return $this->redirectToRoute('login');
    }

    #[Route('/logout', name: 'logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \Exception('This method will be intercepted by the firewall.');
    }
}
