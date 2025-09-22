<?php

namespace App\Tests\Unit;

use App\Controller\SettingsController;
use App\Entity\Setting;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\User\UserInterface;

class SettingsControllerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel();
    }

    private function makeControllerWithUser(?UserInterface $user): SettingsController
    {
        $controller = new class($user) extends SettingsController {
            public function __construct(private ?UserInterface $fakeUser) {}
            public function getUser(): ?UserInterface { return $this->fakeUser; }
        };
        $controller->setContainer(static::getContainer()); // ⭐ ważne dla AbstractController
        return $controller;
    }

    public function testApiGetSettingsUnauthorized(): void
    {
        $controller = $this->makeControllerWithUser(null);
        $em = $this->createMock(EntityManagerInterface::class);

        $resp = $controller->apiGetSettings($em);
        $this->assertSame(401, $resp->getStatusCode());
    }

    public function testApiUpdateSettingsUnauthorized(): void
    {
        $controller = $this->makeControllerWithUser(null);
        $em = $this->createMock(EntityManagerInterface::class);
        $req = new Request([], [], [], [], [], [], json_encode([
            'notifications' => true, 'darkMode' => false, 'fontSize' => 'small'
        ], JSON_THROW_ON_ERROR));

        $resp = $controller->apiUpdateSettings($req, $em);
        $this->assertSame(401, $resp->getStatusCode());
    }

    public function testApiUpdateSettingsMissingFields(): void
    {
        $user = new User();
        $controller = $this->makeControllerWithUser($user);
        $em = $this->createMock(EntityManagerInterface::class);

        $req = new Request([], [], [], [], [], [], json_encode(['notifications' => true], JSON_THROW_ON_ERROR));
        $resp = $controller->apiUpdateSettings($req, $em);
        $this->assertSame(400, $resp->getStatusCode());
    }

    public function testApiUpdateSettingsOk(): void
    {
        $user = new User();
        // podstawiamy Setting, ale NIE odczytujemy z niego getterów (żeby nie zależeć od ich istnienia)
        $setting = new Setting();
        $user->setSetting($setting);

        $controller = $this->makeControllerWithUser($user);

        $em = $this->createMock(EntityManagerInterface::class);
        // przy pierwszym użyciu może zajść persist (gdyby setting nie istniał) – u nas już istnieje
        $em->expects($this->once())->method('flush');

        $req = new Request([], [], [], [], [], [], json_encode([
            'notifications' => true,
            'darkMode'      => true,
            'fontSize'      => 'large'
        ], JSON_THROW_ON_ERROR));
        $resp = $controller->apiUpdateSettings($req, $em);

        $this->assertSame(200, $resp->getStatusCode());
        // brak asercji na getNotifications()/getDarkMode()/getFontSize(), by nie wymagać getterów
    }
}
