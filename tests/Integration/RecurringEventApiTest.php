<?php

namespace App\Tests\Integration;

use App\Entity\RecurringEvent;
use App\Entity\User;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class RecurringEventApiTest extends WebTestCase
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
        return $data['token'];
    }

    public function testRecurringEventsWebRedirectsForAnonymous(): void
    {
        $client = static::createClient();
        $client->request('GET', '/recurring-events');
        $this->assertTrue(in_array($client->getResponse()->getStatusCode(), [302,303], true));
        $this->assertStringContainsString('/login', (string) $client->getResponse()->headers->get('Location'));
    }

    public function testApiRecurringEventsFlow(): void
    {
        $client   = static::createClient();
        $email    = 'user+'.uniqid('', true).'@example.com';
        $password = 'Secret123!';

        $this->register($client, $email, $password);
        $token = $this->loginAndGetToken($client, $email, $password);

        // 1) Pusta lista
        $client->request('GET', '/api/recurring-events', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT'        => 'application/json',
        ]);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $list = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($list);

        // 2) Dodajemy RecurringEvent bezpośrednio w DB (szybciej i pewniej niż przez HTML)
        /** @var EntityManagerInterface $em */
        $em   = static::getContainer()->get(EntityManagerInterface::class);
        /** @var User $user */
        $user = $em->getRepository(User::class)->findOneBy(['email' => $email]);

        $ev = (new RecurringEvent())
            ->setTitle('Cykliczne spotkanie '.uniqid())
            ->setDescription('Opis testowy')
            ->setRecurrencePattern('weekly')
            ->setStartDate(new DateTime('2025-09-08'))
            ->setOwner($user)
            ->setCreatedAt(new DateTimeImmutable())
            ->setUpdatedAt(new DateTime());

        $em->persist($ev);
        $em->flush();
        $eventId = $ev->getId();
        $this->assertNotEmpty($eventId);

        // 3) Lista zawiera nasz event
        $client->request('GET', '/api/recurring-events', server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT'        => 'application/json',
        ]);
        $list2 = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertTrue(
            array_reduce($list2, fn($carry, $row) => $carry || ((int)($row['id'] ?? 0) === (int)$eventId), false),
            'Nowy RecurringEvent powinien być na liście.'
        );

        // 4) Szczegóły
        $client->request('GET', "/api/recurring-events/{$eventId}/details", server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT'        => 'application/json',
        ]);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $details = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame($eventId, (int)($details['id'] ?? 0));
        $this->assertSame('weekly', $details['recurrencePattern'] ?? null);

        // 5) Toggle important
        $client->request('PATCH', "/api/recurring-events/{$eventId}/important", server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT'        => 'application/json',
        ]);
        $this->assertTrue($client->getResponse()->isSuccessful());
        $toggle = json_decode($client->getResponse()->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertArrayHasKey('isImportant', $toggle);
        $this->assertIsBool($toggle['isImportant']);

        // 6) Delete
        $client->request('DELETE', "/api/recurring-events/{$eventId}/delete", server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT'        => 'application/json',
        ]);
        $this->assertTrue($client->getResponse()->isSuccessful());

        // 7) Po usunięciu – details => 404
        $client->request('GET', "/api/recurring-events/{$eventId}/details", server: [
            'HTTP_AUTHORIZATION' => 'Bearer '.$token,
            'HTTP_ACCEPT'        => 'application/json',
        ]);
        $this->assertSame(404, $client->getResponse()->getStatusCode());
    }
}
