<?php

namespace App\Entity;

use App\Repository\ProjectMembershipRepository;
use Doctrine\ORM\Mapping as ORM;

// Join entity carrying attributes (User <-> Project many-to-many with data)
#[ORM\Entity(repositoryClass: ProjectMembershipRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_MEMBERSHIP', fields: ['user', 'project'])]
class ProjectMembership
{
    public const ROLE_LEAD = 'lead';
    public const ROLE_CONTRIBUTOR = 'contributor';
    public const ROLE_VIEWER = 'viewer';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\ManyToOne(inversedBy: 'memberships')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\Column(length: 20)]
    private string $internalRole = self::ROLE_CONTRIBUTOR;

    #[ORM\Column]
    private ?\DateTimeImmutable $joinedAt = null;

    public function __construct()
    {
        $this->joinedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    public function getInternalRole(): string
    {
        return $this->internalRole;
    }

    public function setInternalRole(string $internalRole): static
    {
        $this->internalRole = $internalRole;

        return $this;
    }

    public function getJoinedAt(): ?\DateTimeImmutable
    {
        return $this->joinedAt;
    }

    public function setJoinedAt(\DateTimeImmutable $joinedAt): static
    {
        $this->joinedAt = $joinedAt;

        return $this;
    }
}
