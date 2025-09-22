<?php

namespace App\Tests\Unit;

use App\Controller\MeetingController;
use App\Entity\Contact;
use App\Entity\Event;
use App\Entity\User;
use App\Repository\ContactRepository;
use App\Repository\EventRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class MeetingControllerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    private function makeControllerWithUser(?UserInterface $user): MeetingController
    {
        $ctrl = new class($user) extends MeetingController {
            public function __construct(private ?UserInterface $fakeUser) {}
            public function getUser(): ?UserInterface { return $this->fakeUser; }
        };
        $ctrl->setContainer(static::getContainer());
        return $ctrl;
    }

    public function testApiMeetingsReturnsEvents(): void
    {
        $user = new User();

        $event = new Event();
        if (method_exists($event, 'setUserE')) { $event->setUserE($user); }
        if (method_exists($event, 'setTitle'))  { $event->setTitle('Tytuł'); }
        if (method_exists($event, 'setDescription')) { $event->setDescription('Opis'); }
        if (method_exists($event, 'setDate'))   { $event->setDate(new DateTime('2025-09-08 10:00:00')); }

        /** @var EventRepository&MockObject $eventRepo */
        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo->method('findBy')->willReturn([$event]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(function (string $class) use ($eventRepo) {
            return $eventRepo; // w tym teście używamy tylko EventRepository
        });

        $controller = $this->makeControllerWithUser($user);
        $resp = $controller->apiMeetings($em);

        $this->assertSame(200, $resp->getStatusCode());
        $data = json_decode($resp->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertSame('Tytuł', $data[0]['title'] ?? null);
    }

    public function testApiCreateMeeting401WhenNoUser(): void
    {
        $controller = $this->makeControllerWithUser(null);
        $em = $this->createMock(EntityManagerInterface::class);

        $req = new Request(content: json_encode([
            'title' => 'A', 'description' => 'B', 'date' => '2025-09-08 12:00:00'
        ], JSON_THROW_ON_ERROR));

        $resp = $controller->apiCreateMeeting($req, $em);
        $this->assertSame(401, $resp->getStatusCode());
    }

    public function testApiCreateMeeting400WhenMissingFields(): void
    {
        $controller = $this->makeControllerWithUser(new User());
        $em = $this->createMock(EntityManagerInterface::class);

        $req = new Request(content: json_encode(['date' => '2025-09-08 12:00:00'], JSON_THROW_ON_ERROR));
        $resp = $controller->apiCreateMeeting($req, $em);
        $this->assertSame(400, $resp->getStatusCode());
    }

    public function testApiCreateMeetingPersistsAndLinksContacts(): void
    {
        $user = new User();
        $controller = $this->makeControllerWithUser($user);

        // kontakt do podpięcia
        $contact = new Contact();
        if (method_exists($contact, 'setName')) { $contact->setName('Anna'); }

        /** @var ContactRepository&MockObject $contactRepo */
        $contactRepo = $this->createMock(ContactRepository::class);
        $contactRepo->method('find')->willReturn($contact);

        /** @var EventRepository&MockObject $eventRepo */
        $eventRepo = $this->createMock(EventRepository::class);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(function (string $class) use ($contactRepo, $eventRepo) {
            if (str_contains($class, 'Contact')) {
                return $contactRepo;
            }
            return $eventRepo;
        });

        $em->expects($this->atLeastOnce())->method('persist');
        $em->expects($this->once())->method('flush');

        $payload = [
            'title'       => 'Project Meeting',
            'description' => 'Discuss project updates',
            'date'        => '2025-09-08 14:00:00',
            'contacts'    => [1],
        ];

        $req = new Request(content: json_encode($payload, JSON_THROW_ON_ERROR));
        $resp = $controller->apiCreateMeeting($req, $em);

        $this->assertSame(201, $resp->getStatusCode());
    }

    public function testApiToggleMeetingImportantNotFound(): void
    {
        $controller = $this->makeControllerWithUser(new User());

        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo->method('find')->with(999)->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($eventRepo);

        $resp = $controller->apiToggleMeetingImportant(999, $em);
        $this->assertSame(404, $resp->getStatusCode());
    }

    public function testApiToggleMeetingImportantToggles(): void
    {
        $controller = $this->makeControllerWithUser(new User());

        $event = new Event();
        if (method_exists($event, 'setIsImportant')) { $event->setIsImportant(false); }

        $eventRepo = $this->createMock(EventRepository::class);
        $eventRepo->method('find')->with(1)->willReturn($event);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($eventRepo);
        $em->expects($this->once())->method('flush');

        $resp = $controller->apiToggleMeetingImportant(1, $em);
        $this->assertSame(200, $resp->getStatusCode());

        // po toggle powinno być true
        $this->assertTrue($event->getIsImportant());
    }
}
