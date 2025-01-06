<?php

namespace App\Entity;

use App\Repository\SettingRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SettingRepository::class)]
class Setting
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(cascade: ['persist', 'remove'])]
    private ?User $useruserS = null;

    #[ORM\Column]
    private ?bool $notifications = null;

    #[ORM\OneToOne(inversedBy: 'setting')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUseruserS(): ?User
    {
        return $this->useruserS;
    }

    public function setUseruserS(?User $useruserS): static
    {
        $this->useruserS = $useruserS;

        return $this;
    }

    public function isNotifications(): ?bool
    {
        return $this->notifications;
    }

    public function setNotifications(bool $notifications): static
    {
        $this->notifications = $notifications;

        return $this;
    }
}
