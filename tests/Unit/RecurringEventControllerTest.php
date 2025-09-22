<?php

namespace App\Tests\Unit;

use App\Controller\RecurringEventController;
use App\Entity\RecurringEvent;
use App\Entity\User;
use App\Repository\RecurringEventRepository;
use DateTime;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Security\Core\User\UserInterface;

class RecurringEventControllerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    private function makeControllerWithUser(?UserInterface $user): RecurringEventController
    {
        $ctrl = new class($user) extends RecurringEventController {
            public function __construct(private ?UserInterface $fakeUser) {}
            public function getUser(): ?UserInterface { return $this->fakeUser; }
        };
        $ctrl->setContainer(static::getContainer());
        return $ctrl;
    }

    public function testApiGetRecurringEventsUnauthorized(): void
    {
        $controller = $this->makeControllerWithUser(null);

        $repo = $this->createMock(RecurringEventRepository::class);
        $em   = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $resp = $controller->apiGetRecurringEvents($em);
        $this->assertSame(401, $resp->getStatusCode());
    }

    public function testApiGetRecurringEventsReturnsData(): void
    {
        $user = new User();

        $e1 = (new RecurringEvent())
            ->setTitle('R1')
            ->setDescription('D1')
            ->setRecurrencePattern('monthly')
            ->setStartDate(new DateTime('2025-09-01'))
            ->setOwner($user)
            ->setCreatedAt(new DateTimeImmutable())
            ->setUpdatedAt(new DateTime());

        /** @var RecurringEventRepository&MockObject $repo */
        $repo = $this->createMock(RecurringEventRepository::class);
        $repo->method('findBy')->willReturn([$e1]);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $controller = $this->makeControllerWithUser($user);
        $resp = $controller->apiGetRecurringEvents($em);

        $this->assertSame(200, $resp->getStatusCode());
        $data = json_decode($resp->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertIsArray($data);
        $this->assertSame('R1', $data[0]['title'] ?? null);
        $this->assertSame('monthly', $data[0]['recurrencePattern'] ?? null);
    }

    public function testApiGetRecurringEventDetailsNotFound(): void
    {
        $controller = $this->makeControllerWithUser(new User());

        $repo = $this->createMock(RecurringEventRepository::class);
        $repo->method('find')->with(999)->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $resp = $controller->apiGetRecurringEventDetails(999, $em);
        $this->assertSame(404, $resp->getStatusCode());
    }

    public function testApiGetRecurringEventDetailsOk(): void
    {
        $controller = $this->makeControllerWithUser(new User());

        $e = (new RecurringEvent())
            ->setTitle('R2')
            ->setDescription('D2')
            ->setRecurrencePattern('weekly')
            ->setStartDate(new DateTime('2025-09-08'))
            ->setCreatedAt(new DateTimeImmutable())
            ->setUpdatedAt(new DateTime());

        $repo = $this->createMock(RecurringEventRepository::class);
        $repo->method('find')->with(1)->willReturn($e);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $resp = $controller->apiGetRecurringEventDetails(1, $em);
        $this->assertSame(200, $resp->getStatusCode());
        $data = json_decode($resp->getContent(), true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('R2', $data['title'] ?? null);
        $this->assertSame('weekly', $data['recurrencePattern'] ?? null);
    }

    public function testApiToggleImportantNotFound(): void
    {
        $controller = $this->makeControllerWithUser(new User());

        $repo = $this->createMock(RecurringEventRepository::class);
        $repo->method('find')->with(777)->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $resp = $controller->apiToggleImportantRecurringEvent(777, $em);
        $this->assertSame(404, $resp->getStatusCode());
    }

    public function testApiToggleImportantOk(): void
    {
        $controller = $this->makeControllerWithUser(new User());

        $e = new RecurringEvent();
        $e->setIsImportant(false);

        $repo = $this->createMock(RecurringEventRepository::class);
        $repo->method('find')->with(1)->willReturn($e);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->expects($this->once())->method('flush');

        $resp = $controller->apiToggleImportantRecurringEvent(1, $em);
        $this->assertSame(200, $resp->getStatusCode());
        $this->assertTrue($e->getIsImportant());
    }

    public function testApiDeleteRecurringEvent(): void
    {
        $controller = $this->makeControllerWithUser(new User());

        $e = new RecurringEvent();

        $repo = $this->createMock(RecurringEventRepository::class);
        $repo->method('find')->with(5)->willReturn($e);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);
        $em->expects($this->once())->method('remove')->with($e);
        $em->expects($this->once())->method('flush');

        $resp = $controller->apiDeleteRecurringEvent(5, $em);
        $this->assertSame(200, $resp->getStatusCode());
    }
}
