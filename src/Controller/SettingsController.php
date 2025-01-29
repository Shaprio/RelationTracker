<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\User;
use App\Entity\Setting;

class SettingsController extends AbstractController
{
    #[Route('/settings', name: 'app_settings', methods: ['GET'])]
    public function settings(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('login');
        }

        $setting = $user->getSetting();
        if (!$setting) {
            $setting = new Setting();
            $setting->setUser($user);
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

        $username = $request->request->get('username');
        $email    = $request->request->get('email');
        $password = $request->request->get('password');

        $user->setName($username);
        $user->setEmail($email);

        if ($password) {
            // Tutaj ewentualne hashowanie i zapis
            // $hashedPassword = ...
            // $user->setPassword($hashedPassword);
        }

        $setting = $user->getSetting();
        if (!$setting) {
            $setting = new Setting();
            $setting->setUser($user);
            $em->persist($setting);
        }

        $notifications = $request->request->get('notifications') === 'on';
        $setting->setNotifications($notifications);

        $darkMode = $request->request->get('darkMode') === 'on';
        $setting->setDarkMode($darkMode);

        $fontSize = $request->request->get('fontSize', 'medium');
        $setting->setFontSize($fontSize);

        $em->flush();

        $this->addFlash('success', 'Settings updated successfully.');

        return $this->redirectToRoute('app_settings');
    }
}
