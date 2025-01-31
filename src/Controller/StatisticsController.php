<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Doctrine\ORM\EntityManagerInterface;
use OpenApi\Attributes as OA;
use App\Entity\User;
use App\Entity\Contact;
use App\Entity\Event;
use App\Entity\RecurringEvent;


class StatisticsController extends AbstractController
{

    #[Route('/statistics', name: 'statistics', methods: ['GET'])]
    public function statistics(EntityManagerInterface $entityManager): Response
    {
        $user = $this->getUser();
        if (!$user) {
            return $this->redirectToRoute('login');
        }

        $contactCount = $entityManager->getRepository(Contact::class)
            ->count(['userName' => $user]);

        $eventCount = $entityManager->getRepository(Event::class)
            ->count(['userE' => $user]);

        $recurringEventCount = $entityManager->getRepository(RecurringEvent::class)
            ->count(['owner' => $user]);

        // Zlicz interakcje
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

        // TOP 5 kontaktów, które inicjują najwięcej interakcji
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
            'contactCount'        => $contactCount,
            'eventCount'          => $eventCount,
            'recurringEventCount' => $recurringEventCount,
            'interactionCount'    => $interactionCount,
            'topContacts'         => $topContacts,
            'initiatedByUser'     => $initiatedByUser,
            'initiatedByContact'  => $initiatedByContact,
        ]);
    }


    #[Route('/api/statistics', name: 'api_statistics', methods: ['GET'])]
    #[OA\Get(
        path: '/api/statistics',
        summary: 'Get user statistics',
        security: [['Bearer' => []]],
        responses: [
            new OA\Response(
                response: 200,
                description: 'User statistics retrieved',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'contactCount', type: 'integer', example: 20),
                        new OA\Property(property: 'eventCount', type: 'integer', example: 10),
                        new OA\Property(property: 'recurringEventCount', type: 'integer', example: 5),
                        new OA\Property(property: 'interactionCount', type: 'integer', example: 15),
                        new OA\Property(
                            property: 'topContacts',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'name', type: 'string', example: 'John Doe'),
                                    new OA\Property(property: 'interaction_count', type: 'integer', example: 5)
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'initiatedByUser',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'name', type: 'string', example: 'Alice'),
                                    new OA\Property(property: 'count', type: 'integer', example: 3)
                                ]
                            )
                        ),
                        new OA\Property(
                            property: 'initiatedByContact',
                            type: 'array',
                            items: new OA\Items(
                                properties: [
                                    new OA\Property(property: 'name', type: 'string', example: 'Bob'),
                                    new OA\Property(property: 'count', type: 'integer', example: 2)
                                ]
                            )
                        )
                    ]
                )
            ),
            new OA\Response(response: 401, description: 'Unauthorized')
        ]
    )]
    public function apiStatistics(EntityManagerInterface $entityManager): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->getUser();
        if (!$user) {
            return $this->json(['error' => 'Unauthorized'], Response::HTTP_UNAUTHORIZED);
        }

        $statistics = $this->getUserStatistics($entityManager, $user);

        return $this->json($statistics);
    }

    private function getUserStatistics(EntityManagerInterface $entityManager, User $user): array
    {
        $contactCount = $entityManager->getRepository(Contact::class)
            ->count(['userName' => $user]);

        $eventCount = $entityManager->getRepository(Event::class)
            ->count(['userE' => $user]);

        $recurringEventCount = $entityManager->getRepository(RecurringEvent::class)
            ->count(['owner' => $user]);

        $interactionCount = $entityManager->createQuery("
            SELECT COUNT(i.id)
            FROM App\Entity\Interaction i
            JOIN i.contact c
            WHERE c.userName = :user
        ")
            ->setParameter('user', $user)
            ->getSingleScalarResult();

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

        return [
            'contactCount' => $contactCount,
            'eventCount' => $eventCount,
            'recurringEventCount' => $recurringEventCount,
            'interactionCount' => $interactionCount,
            'topContacts' => $topContacts,
            'initiatedByUser' => $initiatedByUser,
            'initiatedByContact' => $initiatedByContact,
        ];
    }
}
