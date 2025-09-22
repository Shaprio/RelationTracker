<?php

namespace App\Tests\Unit;

use App\Controller\DashboardController;
use App\Entity\Event;
use App\Entity\RecurringEvent;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\RecurringEventRepository;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class DashboardControllerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testGetTodayEventsUnauthorized(): void
    {
        // Kontroler z getUser() => null (zgodna sygnatura!)
        $controller = new class extends DashboardController {
            public function getUser(): ?UserInterface { return null; }
        };
        $controller->setContainer(static::getContainer());

        $em = $this->createMock(EntityManagerInterface::class);

        $response = $controller->getTodayEvents(new Request(), $em);
        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(401, $response->getStatusCode());
    }

    public function testGetTodayEventsMergesMeetingsAndRecurring(): void
    {
        $user = new User(); // zakładam, że App\Entity\User implements UserInterface

        // Kontroler z podstawionym użytkownikiem (zgodna sygnatura!)
        $controller = new class($user) extends DashboardController {
            public function __construct(private UserInterface $fakeUser) {}
            public function getUser(): ?UserInterface { return $this->fakeUser; }
        };
        $controller->setContainer(static::getContainer());

        // Dane testowe
        $today = new DateTime('2025-09-08 10:00:00');

        $event = new Event();
        if (method_exists($event, 'setTitle')) { $event->setTitle('Spotkanie A'); }
        if (method_exists($event, 'setDate'))  { $event->setDate($today); }

        // 🔧 Mocki KONKRETNYCH repozytoriów aplikacji
        /** @var EventRepository&MockObject $eventRepo */
        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo->method('findByDateForUser')->willReturn([$event]);

        /** @var RecurringEventRepository&MockObject $recRepo */
        $recRepo = $this->createMock(RecurringEventRepository::class);
        $recRepo->method('getRecurringEventsOnDate')->willReturn([
            ['title' => 'Urodziny B', 'date' => '2025-09-08 00:00', 'type' => 'Recurring'],
        ]);

        // EM zwraca nasze mocki dla odpowiednich encji
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')
            ->willReturnCallback(function (string $class) use ($eventRepo, $recRepo) {
                return match ($class) {
                    Event::class          => $eventRepo,
                    RecurringEvent::class => $recRepo,
                    default               => null, // nieużywane w tym teście
                };
            });

        // Request z datą (żeby nie polegać na „dzisiaj”)
        $req = new Request(query: ['date' => '2025-09-08']);

        $resp = $controller->getTodayEvents($req, $em);

        $this->assertSame(200, $resp->getStatusCode());
        $data = json_decode($resp->getContent(), true, 512, JSON_THROW_ON_ERROR);

        $this->assertIsArray($data);
        // powinny być 2 wpisy: 1 z meetingów + 1 z recurring
        $this->assertCount(2, $data);

        $titles = array_column($data, 'title');
        $this->assertContains('Spotkanie A', $titles);
        $this->assertContains('Urodziny B', $titles);
    }
}
