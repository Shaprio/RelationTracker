<?php

namespace App\Tests\Integration;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class SecurityApiTest extends WebTestCase
{
    private function register($client, string $email, string $password = 'Secret123!', string $name = 'Test User'): void
    {
        $client->request(
            'POST',
            '/api/register',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode([
                'email'    => $email,
                'password' => $password,
                'name'     => $name,
            ], JSON_THROW_ON_ERROR)
        );

        $status = $client->getResponse()->getStatusCode();
        self::assertTrue(
            in_array($status, [200, 201, 409], true),
            "Rejestracja powinna zwrócić 200/201 (lub 409 jeśli istnieje). Otrzymano: $status\n".$client->getResponse()->getContent()
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

        self::assertTrue($client->getResponse()->isSuccessful(), 'Login nieudany: '.$client->getResponse()->getContent());
        $data  = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $token = $data['token'] ?? null;
        self::assertNotEmpty($token, 'Brak tokenu w odpowiedzi logowania.');
        return $token;
    }

    public function testRegisterLoginLogoutFlow(): void
    {
        $client   = static::createClient();
        $email    = 'user+'.uniqid('', true).'@example.com';
        $password = 'Secret123!';

        // Rejestracja
        $this->register($client, $email, $password);

        // Logowanie → token
        $token = $this->loginAndGetToken($client, $email, $password);

        // Logout (u Ciebie to prosty 200)
        $client->request(
            'POST',
            '/api/logout',
            server: ['HTTP_ACCEPT' => 'application/json', 'HTTP_AUTHORIZATION' => 'Bearer '.$token]
        );
        self::assertTrue($client->getResponse()->isSuccessful(), 'Logout powinien zwrócić 200.');
    }

    public function testForgotAndResetPasswordFlow(): void
    {
        $client   = static::createClient();
        $email    = 'user+'.uniqid('', true).'@example.com';
        $password = 'Secret123!';

        // Rejestracja usera
        $this->register($client, $email, $password);

        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        // 1) /forgot-password (POST)
        $client->request('POST', '/forgot-password', parameters: ['email' => $email]);
        $this->assertTrue($client->getResponse()->isSuccessful(), 'POST /forgot-password powinno zwrócić 200.');

        // 👇 ZAMIAST refresh(): wyczyść EM i pobierz użytkownika ponownie
        $em->clear();
        /** @var User $user */
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertInstanceOf(User::class, $user);
        $token = $user->getResetToken();
        $this->assertNotEmpty($token, 'Po zgłoszeniu resetu user powinien mieć ustawiony resetToken.');

        // 2) /reset-password/{token} (POST)
        $client->request('POST', '/reset-password/'.$token, parameters: ['password' => 'NewPass123!']);
        $this->assertTrue(in_array($client->getResponse()->getStatusCode(), [302, 303], true), 'Reset powinien przekierować na /login.');

        // 👇 ponownie: wyczyść i pobierz usera z DB
        $em->clear();
        /** @var User $user2 */
        $user2 = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        $this->assertInstanceOf(User::class, $user2);
        $this->assertNull($user2->getResetToken(), 'Po resecie resetToken powinien być wyczyszczony.');
    }

}

