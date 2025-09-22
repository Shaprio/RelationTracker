<?php

namespace App\Tests\Integration;

use App\Entity\Contact;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ContactApiTest extends WebTestCase
{
    private function registerUser($client, string $email, string $password = 'Secret123!'): void
    {
        $client->request(
            'POST',
            '/api/register',
            server: ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'],
            content: json_encode([
                'email'    => $email,
                'password' => $password,
                'name'     => 'Test User',
            ], JSON_THROW_ON_ERROR)
        );

        $status = $client->getResponse()->getStatusCode();
        // 200/201 OK, 409 – gdy user już istnieje
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

        self::assertTrue(
            $client->getResponse()->isSuccessful(),
            "Login nieudany: ".$client->getResponse()->getContent()
        );

        $data = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $token = $data['token'] ?? null;
        self::assertNotEmpty($token, "Brak tokenu w odpowiedzi logowania: ".$client->getResponse()->getContent());

        return $token;
    }

    public function testListAndDetailsWithExistingContact(): void
    {
        $client   = static::createClient();
        $email    = 'user+'.uniqid('', true).'@example.com';
        $password = 'Secret123!';

        // 1) Rejestracja + login
        $this->registerUser($client, $email, $password);
        $token = $this->loginAndGetToken($client, $email, $password);

        // 2) Utworzenie kontaktu bezpośrednio w bazie (ominie endpoint POST /api/friends)
        /** @var EntityManagerInterface $em */
        $em = static::getContainer()->get(EntityManagerInterface::class);

        /** @var User $user */
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);
        self::assertInstanceOf(User::class, $user, 'Użytkownik powinien istnieć po rejestracji.');

        $contact = (new Contact())
            ->setUserName($user)
            ->setName('Anna Nowak')
            ->setEmailC('anna.'.uniqid().'@example.com')
            ->setPhone('123456789');

        $em->persist($contact);
        $em->flush();
        $contactId = $contact->getId();
        self::assertNotEmpty($contactId, 'Kontakt powinien dostać ID.');

        // 3) GET /api/friends – lista powinna zawierać naszego kontakt
        $client->request('GET', '/api/friends', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT'        => 'application/json',
        ]);
        self::assertTrue(
            $client->getResponse()->isSuccessful(),
            "GET /api/friends powinno zwrócić 200. Odpowiedź:\n".$client->getResponse()->getContent()
        );

        $list = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($list, 'Lista kontaktów powinna być tablicą.');
        self::assertTrue(
            array_reduce($list, fn($carry, $row) => $carry || ((int)($row['id'] ?? 0) === (int)$contactId), false),
            'Nowo dodany kontakt (przez EM) powinien być na liście /api/friends.'
        );

        // 4) GET /api/friends/{id}/details – szczegóły powinny odpowiadać danym encji
        $client->request('GET', "/api/friends/{$contactId}/details", server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT'        => 'application/json',
        ]);
        self::assertTrue(
            $client->getResponse()->isSuccessful(),
            "GET /api/friends/{$contactId}/details powinno zwrócić 200. Odpowiedź:\n".$client->getResponse()->getContent()
        );

        $details = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        self::assertSame($contactId, (int)($details['id'] ?? 0));
        self::assertSame('Anna Nowak', $details['name'] ?? null);
        self::assertSame($contact->getEmailC(), $details['emailC'] ?? null);
        self::assertSame('123456789', $details['phone'] ?? null);
    }
}
