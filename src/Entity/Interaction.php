<?php

namespace App\Entity;

use App\Repository\InteractionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: InteractionRepository::class)]
class Interaction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Contact::class, inversedBy: 'interactions')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Contact $contact = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private ?\DateTimeImmutable $interactionDate = null;

    #[ORM\Column(length: 50)]
    private ?string $initiatedBy = null; // "user" lub "contact"

    public function getId(): ?int
    {
        return $this->id;
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

    public function getInteractionDate(): ?\DateTimeImmutable
    {
        return $this->interactionDate;
    }

    public function setInteractionDate(\DateTimeImmutable $interactionDate): self
    {
        $this->interactionDate = $interactionDate;

        return $this;
    }

    public function getInitiatedBy(): ?string
    {
        return $this->initiatedBy;
    }

    public function setInitiatedBy(string $initiatedBy): self
    {
        $this->initiatedBy = $initiatedBy;

        return $this;
    }
}

