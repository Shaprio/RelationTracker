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

    // Web - Aktualizacja ustawień
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
            // Hashowanie hasła w razie potrzeby
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

    // API - Pobranie ustawień użytkownika
    #[Route('/api/settings', name: 'api_settings', methods: ['GET'])]
    #[OA\Get(
        path: '/api/settings',
        summary: 'Get user settings',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User settings retrieved',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'notifications', type: 'boolean', example: true),
                        new OA\Property(property: 'darkMode', type: 'boolean', example: false),
                        new OA\Property(property: 'fontSize', type: 'string', example: 'medium')
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function apiGetSettings(EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $setting = $user->getSetting();
        if (!$setting) {
            $setting = new Setting();
            $setting->setUser($user);
            $em->persist($setting);
            $em->flush();
        }

        return $this->json([
            'notifications' => $setting->getNotifications(),
            'darkMode' => $setting->getDarkMode(),
            'fontSize' => $setting->getFontSize(),
        ]);
    }

    // API - Aktualizacja ustawień użytkownika
    #[Route('/api/settings/update', name: 'api_settings_update', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/settings/update',
        summary: 'Update user settings',
        security: [['Bearer' => []]],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'notifications', type: 'boolean', example: true),
                    new OA\Property(property: 'darkMode', type: 'boolean', example: false),
                    new OA\Property(property: 'fontSize', type: 'string', example: 'medium')
                ]
            )
        ),
        responses: [
            new OA\Response(response: 200, description: 'Settings updated'),
            new OA\Response(response: 400, description: 'Invalid request'),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function apiUpdateSettings(Request $request, EntityManagerInterface $em): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $data = json_decode($request->getContent(), true);

        if (!isset($data['notifications'], $data['darkMode'], $data['fontSize'])) {
            return $this->json(['error' => 'Missing required fields'], Response::HTTP_BAD_REQUEST);
        }

        $setting = $user->getSetting();
        if (!$setting) {
            $setting = new Setting();
            $setting->setUser($user);
            $em->persist($setting);
        }

        $setting->setNotifications($data['notifications']);
        $setting->setDarkMode($data['darkMode']);
        $setting->setFontSize($data['fontSize']);

        $em->flush();

        return $this->json(['message' => 'Settings updated']);
    }
}
