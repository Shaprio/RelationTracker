<?php

namespace App\Tests\Unit;

use App\Message\SendEmailMessage;
use PHPUnit\Framework\TestCase;

class SendEmailMessageTest extends TestCase
{
    public function testConstructorAndGetters(): void
    {
        $msg = new SendEmailMessage('anna@example.com', 'Temat', '<b>Treść</b>');

        $this->assertSame('anna@example.com', $msg->getEmail());
        $this->assertSame('Temat', $msg->getSubject());
        $this->assertSame('<b>Treść</b>', $msg->getContent());
    }
}
