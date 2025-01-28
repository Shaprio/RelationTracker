<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class RecurringEventContact
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RecurringEvent::class, inversedBy: 'contacts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?RecurringEvent $recurringEvent = null;

    #[ORM\ManyToOne(targetEntity: Contact::class)]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contact $contact = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecurringEvent(): ?RecurringEvent
    {
        return $this->recurringEvent;
    }

    public function setRecurringEvent(?RecurringEvent $recurringEvent): self
    {
        $this->recurringEvent = $recurringEvent;
        return $this;
    }

    public function getContact(): ?Contact
    {
        return $this->contact;
    }

    public function setContact(?Contact $contact): self
    {
        $this->contact = $contact;
        return $this;
    }
}
