<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class DashboardApiTest extends WebTestCase
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
            'Rejestracja powinna zwrócić 200/201/409: '.$client->getResponse()->getStatusCode().' '.$client->getResponse()->getContent()
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
        $this->assertNotEmpty($data['token']);
        return $data['token'];
    }

    public function testMainPageRedirectsToLoginForAnonymous(): void
    {
        $client = static::createClient();
        $client->request('GET', '/mainPage');
        $this->assertTrue(in_array($client->getResponse()->getStatusCode(), [302, 303], true));
        $this->assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testTodayEventsReturnsArrayForAuthenticated(): void
    {
        $client = static::createClient();
        $email = 'user+'.uniqid('', true).'@example.com';
        $pass  = 'Secret123!';

        $this->register($client, $email, $pass);
        $token = $this->loginAndGetToken($client, $email, $pass);

        $client->request(
            'GET',
            '/api/today-events',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json']
        );
        $this->assertTrue($client->getResponse()->isSuccessful(), 'GET /api/today-events powinno zwrócić 200.');
        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data, 'Oczekiwano tablicy zdarzeń.');
    }

    public function testTodayEventsValidatesDate(): void
    {
        $client = static::createClient();
        $email = 'user+'.uniqid('', true).'@example.com';
        $pass  = 'Secret123!';

        $this->register($client, $email, $pass);
        $token = $this->loginAndGetToken($client, $email, $pass);

        $client->request(
            'GET',
            '/api/today-events?date=not-a-date',
            server: ['HTTP_AUTHORIZATION' => 'Bearer '.$token, 'HTTP_ACCEPT' => 'application/json']
        );

        $this->assertSame(400, $client->getResponse()->getStatusCode(), 'Błędny format daty powinien dać 400.');
        $this->assertStringContainsString('Invalid date format', $client->getResponse()->getContent());
    }
}
