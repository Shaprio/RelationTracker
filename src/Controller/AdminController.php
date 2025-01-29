<?php

namespace App\Controller;

use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AdminController extends AbstractController
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
}
