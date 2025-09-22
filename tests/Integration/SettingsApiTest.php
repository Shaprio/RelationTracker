<?php

namespace App\Tests\Integration;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SettingsApiTest extends WebTestCase
{
    private function register($client, string $email, string $password = 'Secret123!', string $name = 'Test User'): void
    {
        $client->request(
            'POST',
            '/api/register',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => $password, 'name' => $name])
        );
        $this->assertTrue(in_array($client->getResponse()->getStatusCode(), [200,201,409], true));
    }

    private function loginAndGetToken($client, string $email, string $password = 'Secret123!'): string
    {
        $client->request(
            'POST',
            '/api/login',
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['email' => $email, 'password' => $password])
        );
        $this->assertTrue($client->getResponse()->isSuccessful(), 'Login failed: '.$client->getResponse()->getContent());
        $data = json_decode($client->getResponse()->getContent(), true);
        return $data['token'];
    }

    public function testSettingsUpdateFlow(): void
    {
        $client   = static::createClient();
        $email    = 'user+'.uniqid().'@example.com';
        $password = 'Secret123!';

        $this->register($client, $email, $password);
        $token = $this->loginAndGetToken($client, $email, $password);

        // PUT /api/settings/update – poprawne dane
        $client->request(
            'PUT',
            '/api/settings/update',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_ACCEPT'        => 'application/json',
            ],
            content: json_encode([
                'notifications' => true,
                'darkMode'      => false,
                'fontSize'      => 'large',
            ], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(200, $client->getResponse()->getStatusCode(), 'PUT /api/settings/update powinno zwrócić 200');

        // PUT /api/settings/update – brak wymaganych pól -> 400
        $client->request(
            'PUT',
            '/api/settings/update',
            server: [
                'HTTP_AUTHORIZATION' => 'Bearer '.$token,
                'CONTENT_TYPE'       => 'application/json',
                'HTTP_ACCEPT'        => 'application/json',
            ],
            content: json_encode([
                'notifications' => true,
                // 'darkMode' i 'fontSize' pominięte
            ], JSON_THROW_ON_ERROR)
        );
        $this->assertSame(400, $client->getResponse()->getStatusCode(), 'Brak pól powinien zwrócić 400');
    }
}
