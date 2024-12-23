<?php

namespace App\Entity;

use App\Repository\ReminderRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ReminderRepository::class)]
class Reminder
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'reminders')]
    private ?User $userR = null;

    #[ORM\ManyToOne(inversedBy: 'reminders')]
    private ?Contact $contactR = null;

    #[ORM\ManyToOne(inversedBy: 'reminders')]
    private ?Event $eventR = null;

    #[ORM\Column(length: 255)]
    private ?string $message = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $remindAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserR(): ?User
    {
        return $this->userR;
    }

    public function setUserR(?User $userR): static
    {
        $this->userR = $userR;

        return $this;
    }

    public function getContactR(): ?Contact
    {
        return $this->contactR;
    }

    public function setContactR(?Contact $contactR): static
    {
        $this->contactR = $contactR;

        return $this;
    }

    public function getEventR(): ?Event
    {
        return $this->eventR;
    }

    public function setEventR(?Event $eventR): static
    {
        $this->eventR = $eventR;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getRemindAt(): ?\DateTimeInterface
    {
        return $this->remindAt;
    }

    public function setRemindAt(\DateTimeInterface $remindAt): static
    {
        $this->remindAt = $remindAt;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }
}
