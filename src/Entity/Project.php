<?php

namespace App\Entity;

use App\Repository\ProjectRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProjectRepository::class)]
class Project
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_ARCHIVED = 'archived';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'projects')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Choisissez une organisation.')]
    private ?Organization $organization = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    // Short project key, e.g. "TF" for TaskFlow
    #[ORM\Column(length: 10)]
    private ?string $projectKey = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $startDate = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $endDate = null;

    #[ORM\Column(length: 20)]
    private string $status = self::STATUS_ACTIVE;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    /** @var Collection<int, ProjectMembership> */
    #[ORM\OneToMany(targetEntity: ProjectMembership::class, mappedBy: 'project', orphanRemoval: true)]
    private Collection $memberships;

    /** @var Collection<int, Sprint> */
    #[ORM\OneToMany(targetEntity: Sprint::class, mappedBy: 'project', orphanRemoval: true)]
    private Collection $sprints;

    /** @var Collection<int, Task> */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'project', orphanRemoval: true)]
    private Collection $tasks;

    /** @var Collection<int, ActivityLog> */
    #[ORM\OneToMany(targetEntity: ActivityLog::class, mappedBy: 'project', orphanRemoval: true)]
    private Collection $activityLogs;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->memberships = new ArrayCollection();
        $this->sprints = new ArrayCollection();
        $this->tasks = new ArrayCollection();
        $this->activityLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): ?Organization
    {
        return $this->organization;
    }

    public function setOrganization(?Organization $organization): static
    {
        $this->organization = $organization;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getProjectKey(): ?string
    {
        return $this->projectKey;
    }

    public function setProjectKey(string $projectKey): static
    {
        $this->projectKey = $projectKey;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getStartDate(): ?\DateTimeImmutable
    {
        return $this->startDate;
    }

    public function setStartDate(?\DateTimeImmutable $startDate): static
    {
        $this->startDate = $startDate;

        return $this;
    }

    public function getEndDate(): ?\DateTimeImmutable
    {
        return $this->endDate;
    }

    public function setEndDate(?\DateTimeImmutable $endDate): static
    {
        $this->endDate = $endDate;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

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

    /** @return Collection<int, ProjectMembership> */
    public function getMemberships(): Collection
    {
        return $this->memberships;
    }

    public function addMembership(ProjectMembership $membership): static
    {
        if (!$this->memberships->contains($membership)) {
            $this->memberships->add($membership);
            $membership->setProject($this);
        }

        return $this;
    }

    public function removeMembership(ProjectMembership $membership): static
    {
        if ($this->memberships->removeElement($membership) && $membership->getProject() === $this) {
            $membership->setProject(null);
        }

        return $this;
    }

    /** @return Collection<int, Sprint> */
    public function getSprints(): Collection
    {
        return $this->sprints;
    }

    public function addSprint(Sprint $sprint): static
    {
        if (!$this->sprints->contains($sprint)) {
            $this->sprints->add($sprint);
            $sprint->setProject($this);
        }

        return $this;
    }

    public function removeSprint(Sprint $sprint): static
    {
        if ($this->sprints->removeElement($sprint) && $sprint->getProject() === $this) {
            $sprint->setProject(null);
        }

        return $this;
    }

    /** @return Collection<int, Task> */
    public function getTasks(): Collection
    {
        return $this->tasks;
    }

    public function addTask(Task $task): static
    {
        if (!$this->tasks->contains($task)) {
            $this->tasks->add($task);
            $task->setProject($this);
        }

        return $this;
    }

    public function removeTask(Task $task): static
    {
        if ($this->tasks->removeElement($task) && $task->getProject() === $this) {
            $task->setProject(null);
        }

        return $this;
    }

    /** @return Collection<int, ActivityLog> */
    public function getActivityLogs(): Collection
    {
        return $this->activityLogs;
    }

    public function addActivityLog(ActivityLog $activityLog): static
    {
        if (!$this->activityLogs->contains($activityLog)) {
            $this->activityLogs->add($activityLog);
            $activityLog->setProject($this);
        }

        return $this;
    }

    public function removeActivityLog(ActivityLog $activityLog): static
    {
        if ($this->activityLogs->removeElement($activityLog) && $activityLog->getProject() === $this) {
            $activityLog->setProject(null);
        }

        return $this;
    }

    // --- Business helpers (used by Voters, controllers and templates) ---

    public function isArchived(): bool
    {
        return self::STATUS_ARCHIVED === $this->status;
    }

    /** The membership marked as LEAD, if any. */
    public function getLeadMembership(): ?ProjectMembership
    {
        foreach ($this->memberships as $m) {
            if (ProjectMembership::ROLE_LEAD === $m->getInternalRole()) {
                return $m;
            }
        }

        return null;
    }

    public function getLead(): ?User
    {
        return $this->getLeadMembership()?->getUser();
    }

    /** @return list<User> all users who are members of this project */
    public function getMembers(): array
    {
        return array_values(array_filter(array_map(
            static fn (ProjectMembership $m) => $m->getUser(),
            $this->memberships->toArray(),
        )));
    }

    public function hasMember(User $user): bool
    {
        foreach ($this->memberships as $m) {
            if ($m->getUser()?->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }

    public function isLead(User $user): bool
    {
        return $this->getLead()?->getId() === $user->getId();
    }
}
