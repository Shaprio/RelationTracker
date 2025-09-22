<?php

namespace App\Tests\Integration;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class MeetingApiTest extends WebTestCase
{
    private function register($client, string $email, string $password = 'Secret123!', string $name = 'Test User'): void
    {
        $client->request(
            'POST',
            '/api/register',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => $password, 'name' => $name], JSON_THROW_ON_ERROR)
        );
        $this->assertTrue(
            in_array($client->getResponse()->getStatusCode(), [200, 201, 409], true),
            'Rejestracja powinna zwrócić 200/201/409, otrzymano: '.$client->getResponse()->getStatusCode().' '.$client->getResponse()->getContent()
        );
    }

    private function loginAndGetToken($client, string $email, string $password = 'Secret123!'): string
    {
        $client->request(
            'POST',
            '/api/login',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => $password], JSON_THROW_ON_ERROR)
        );
        $this->assertTrue($client->getResponse()->isSuccessful(), 'Login nieudany: '.$client->getResponse()->getContent());
        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('token', $data);
        return $data['token'];
    }

    public function testMeetingsWebRedirectsForAnonymous(): void
    {
        $client = static::createClient();
        $client->request('GET', '/meetings');
        $this->assertTrue(in_array($client->getResponse()->getStatusCode(), [302,303], true));
        $this->assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testMeetingsApiFlow(): void
    {
        $client   = static::createClient();
        $email    = 'user+'.uniqid('', true).'@example.com';
        $password = 'Secret123!';

        $this->register($client, $email, $password);
        $token = $this->loginAndGetToken($client, $email, $password);

        // 1) Pusta lista /api/meetings
        $client->request('GET', '/api/meetings', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT'        => 'application/json',
        ]);
        $this->assertTrue($client->getResponse()->isSuccessful(), 'GET /api/meetings powinno być 200');
        $list = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($list);

        // 2) Utworzenie meetingu (bez kontaktów)
        $payload = [
            'title'       => 'Project Meeting '.uniqid(),
            'description' => 'Discuss project updates',
            'date'        => '2025-09-08 14:00:00',
            // 'contacts' => [ opcjonalnie id kontaktów ]
        ];
        $client->request('POST', '/api/meetings/create',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_ACCEPT'        => 'application/json',
                'CONTENT_TYPE'       => 'application/json',
            ],
            content: json_encode($payload, JSON_THROW_ON_ERROR)
        );
        $this->assertSame(201, $client->getResponse()->getStatusCode(), 'POST /api/meetings/create powinno zwrócić 201');

        // 3) Lista zawiera nowy meeting
        $client->request('GET', '/api/meetings', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT'        => 'application/json',
        ]);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $list2 = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($list2);
        $this->assertTrue(
            array_reduce($list2, fn($carry, $row) => $carry || (($row['title'] ?? '') === $payload['title']), false),
            'Nowy meeting powinien być zwrócony przez /api/meetings.'
        );

        // Zapamiętaj ID do toggle
        $created = array_values(array_filter($list2, fn($row) => ($row['title'] ?? '') === $payload['title']))[0] ?? null;
        $this->assertNotEmpty($created);
        $meetingId = $created['id'] ?? null;
        $this->assertNotEmpty($meetingId, 'Nowy meeting powinien mieć ID.');

        // 4) Walidacja - brak wymaganych pól (tylko data) → 400
        $client->request('POST', '/api/meetings/create',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'HTTP_ACCEPT'        => 'application/json',
                'CONTENT_TYPE'       => 'application/json',
            ],
            content: json_encode(['date' => '2025-09-08 15:00:00'], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(400, $client->getResponse()->getStatusCode(), 'Brak tytułu/opisu powinien dać 400');

        // 5) Toggle important
        $client->request('PATCH', "/api/meetings/{$meetingId}/important", server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT'        => 'application/json',
        ]);
        $this->assertTrue($client->getResponse()->isSuccessful(), 'PATCH /api/meetings/{id}/important powinno być 200');
        $toggle = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('isImportant', $toggle);
        $this->assertIsBool($toggle['isImportant']);
    }
}
