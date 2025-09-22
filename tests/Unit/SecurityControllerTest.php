<?php

namespace App\Tests\Unit;

use App\Controller\SecurityController;
use App\Entity\User;
use App\Message\SendEmailMessage;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\ConstraintViolationListInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SecurityControllerTest extends KernelTestCase
{
    protected function setUp(): void
    {
        self::bootKernel(); // udostępnia static::getContainer()
    }

    /** Pomocniczo: twórz mock repo (EntityRepository ma wymagany typ) */
    private function mockUserRepo(): EntityRepository
    {
        /** @var EntityRepository&MockObject $repo */
        $repo = $this->getMockBuilder(EntityRepository::class)
            ->disableOriginalConstructor()
            ->getMock();

        return $repo;
    }

    public function testApiRegisterSuccess(): void
    {
        $request = new Request(content: json_encode([
            'email'    => 'john@example.com',
            'name'     => 'John',
            'password' => 'Secret123!',
        ], JSON_THROW_ON_ERROR));

        $repo = $this->mockUserRepo();
        $repo->method('findOneBy')->willReturn(null);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(User::class)->willReturn($repo);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('hashed');

        $violations = $this->createMock(ConstraintViolationListInterface::class);
        $violations->method('count')->willReturn(0);
        $validator = $this->createMock(ValidatorInterface::class);
        $validator->method('validate')->willReturn($violations);

        $controller = new SecurityController();
        $controller->setContainer(static::getContainer());

        $resp = $controller->apiRegister($request, $hasher, $em, $validator);

        $this->assertSame(201, $resp->getStatusCode());
    }

    public function testApiRegisterEmailAlreadyExists(): void
    {
        $request = new Request(content: json_encode([
            'email'    => 'john@example.com',
            'name'     => 'John',
            'password' => 'Secret123!',
        ], JSON_THROW_ON_ERROR));

        $existing = new User();

        $repo = $this->mockUserRepo();
        $repo->method('findOneBy')->willReturn($existing);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(User::class)->willReturn($repo);

        $hasher    = $this->createMock(UserPasswordHasherInterface::class);
        $validator = $this->createMock(ValidatorInterface::class);

        $controller = new SecurityController();
        $controller->setContainer(static::getContainer()); // ⭐

        $resp = $controller->apiRegister($request, $hasher, $em, $validator);

        $this->assertSame(400, $resp->getStatusCode());
    }

    public function testApiLoginSuccess(): void
    {
        $request = new Request(content: json_encode([
            'email'    => 'john@example.com',
            'password' => 'Secret123!',
        ], JSON_THROW_ON_ERROR));

        $user = new User();

        $repo = $this->mockUserRepo();
        $repo->method('findOneBy')->with(['email' => 'john@example.com'])->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(User::class)->willReturn($repo);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('isPasswordValid')->with($user, 'Secret123!')->willReturn(true);

        $jwt = $this->createMock(JWTTokenManagerInterface::class);
        $jwt->method('create')->with($user)->willReturn('FAKE.JWT.TOKEN');

        $controller = new SecurityController();
        $controller->setContainer(static::getContainer()); // ⭐

        $resp = $controller->apiLogin($request, $hasher, $em, $jwt);

        $this->assertSame(200, $resp->getStatusCode());
        $this->assertStringContainsString('FAKE.JWT.TOKEN', $resp->getContent());
    }

    public function testApiLoginInvalidCredentials(): void
    {
        $request = new Request(content: json_encode([
            'email'    => 'john@example.com',
            'password' => 'bad',
        ], JSON_THROW_ON_ERROR));

        $repo = $this->mockUserRepo();
        $repo->method('findOneBy')->willReturn(null); // brak usera

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(User::class)->willReturn($repo);

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $jwt    = $this->createMock(JWTTokenManagerInterface::class);

        $controller = new SecurityController();
        $controller->setContainer(static::getContainer()); // ⭐

        $resp = $controller->apiLogin($request, $hasher, $em, $jwt);

        $this->assertSame(401, $resp->getStatusCode());
    }

    public function testForgotPasswordDispatchesEmailAndSetsToken(): void
    {
        // symulujemy POST formularza
        $request = new Request([], ['email' => 'john@example.com'], [], [], [], ['REQUEST_METHOD' => 'POST']);

        $user = new User();
        $user->setEmail('john@example.com');

        $repo = $this->mockUserRepo();
        $repo->method('findOneBy')->with(['email' => 'john@example.com'])->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(User::class)->willReturn($repo);
        $em->expects($this->once())->method('flush');

        // ⭐ Messenger: dispatch musi zwrócić Envelope (final) – zwracamy prawdziwy
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn($msg) => $msg instanceof SendEmailMessage))
            ->willReturn(new Envelope(new SendEmailMessage('to@example.com', 'subj', 'body')));

        $urlGen = $this->createMock(UrlGeneratorInterface::class);
        $urlGen->method('generate')->willReturn('http://example.com/reset');

        $controller = new SecurityController();
        $controller->setContainer(static::getContainer()); // ⭐

        $response = $controller->forgotPassword($request, $em, $bus, $urlGen);

        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertNotNull($user->getResetToken());
    }

    public function testResetPasswordChangesPasswordAndClearsToken(): void
    {
        $user  = new User();
        $user->setEmail('john@example.com');
        $user->setResetToken('ABC');

        $repo = $this->mockUserRepo();
        $repo->method('findOneBy')->with(['resetToken' => 'ABC'])->willReturn($user);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->with(User::class)->willReturn($repo);
        $em->expects($this->once())->method('flush');

        $hasher = $this->createMock(UserPasswordHasherInterface::class);
        $hasher->method('hashPassword')->willReturn('NEW_HASH');

        // symulujemy POST z nowym hasłem
        $request = new Request([], ['password' => 'NewPass123!'], [], [], [], ['REQUEST_METHOD' => 'POST']);

        $controller = new SecurityController();
        $controller->setContainer(static::getContainer()); // ⭐

        $response = $controller->resetPassword($request, 'ABC', $em, $hasher);

        // redirect do /login
        $this->assertTrue(in_array($response->getStatusCode(), [302,303], true));
        $this->assertNull($user->getResetToken());
        $this->assertSame('NEW_HASH', $user->getPassword());
    }
}
