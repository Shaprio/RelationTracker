<?php

namespace App\MessageHandler;

use App\Message\SendEmailMessage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;

#[AsMessageHandler]
class SendEmailMessageHandler {
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer) {
        $this->mailer = $mailer;
    }

    public function __invoke(SendEmailMessage $message): void {
        try {
            $email = (new Email())
                ->from('no-reply@yourapp.com')
                ->to($message->getEmail())
                ->subject($message->getSubject())
                ->html($message->getContent());

            $this->mailer->send($email);
        } catch (TransportExceptionInterface $e) {
            // Obsługa błędów wysyłki e-maila
            error_log('Błąd wysyłki emaila: ' . $e->getMessage());
        }
    }
}

