<?php

namespace App\Entity;

use App\Repository\TaskRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

// Single Table Inheritance root: one `task` table, discriminated by `type`.
#[ORM\Entity(repositoryClass: TaskRepository::class)]
#[ORM\InheritanceType('SINGLE_TABLE')]
#[ORM\DiscriminatorColumn(name: 'type', type: 'string', length: 20)]
#[ORM\DiscriminatorMap([
    'bug' => BugTask::class,
    'feature' => FeatureTask::class,
    'story' => StoryTask::class,
])]
abstract class Task
{
    public const STATUS_TODO = 'todo';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_IN_REVIEW = 'in_review';
    public const STATUS_DONE = 'done';

    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::STATUS_TODO, self::STATUS_IN_PROGRESS, self::STATUS_IN_REVIEW, self::STATUS_DONE])]
    private string $status = self::STATUS_TODO;

    #[ORM\Column(length: 20)]
    #[Assert\Choice(choices: [self::PRIORITY_LOW, self::PRIORITY_MEDIUM, self::PRIORITY_HIGH])]
    private string $priority = self::PRIORITY_MEDIUM;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Project $project = null;

    #[ORM\ManyToOne(inversedBy: 'tasks')]
    private ?Sprint $sprint = null;

    #[ORM\ManyToOne]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $author = null;

    #[ORM\ManyToOne]
    private ?User $assignee = null;

    #[ORM\ManyToOne(inversedBy: 'subtasks')]
    private ?Task $parent = null;

    /** @var Collection<int, Task> */
    #[ORM\OneToMany(targetEntity: Task::class, mappedBy: 'parent')]
    private Collection $subtasks;

    /** @var Collection<int, TaskComment> */
    #[ORM\OneToMany(targetEntity: TaskComment::class, mappedBy: 'task', orphanRemoval: true)]
    private Collection $comments;

    /** @var Collection<int, TimeEntry> */
    #[ORM\OneToMany(targetEntity: TimeEntry::class, mappedBy: 'task', orphanRemoval: true)]
    private Collection $timeEntries;

    /** @var Collection<int, Document> */
    #[ORM\OneToMany(targetEntity: Document::class, mappedBy: 'task', orphanRemoval: true)]
    private Collection $documents;

    /** @var Collection<int, Label> */
    #[ORM\ManyToMany(targetEntity: Label::class, inversedBy: 'tasks')]
    #[ORM\JoinTable(name: 'task_label')]
    private Collection $labels;

    /** @var Collection<int, User> Users watching this task for notifications */
    #[ORM\ManyToMany(targetEntity: User::class)]
    #[ORM\JoinTable(name: 'task_watcher')]
    private Collection $watchers;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->subtasks = new ArrayCollection();
        $this->comments = new ArrayCollection();
        $this->timeEntries = new ArrayCollection();
        $this->documents = new ArrayCollection();
        $this->labels = new ArrayCollection();
        $this->watchers = new ArrayCollection();
    }

    // Discriminator value, e.g. "bug" / "feature" / "story"
    abstract public function getType(): string;

    #[Groups(['task:list', 'task:read'])]
    public function getId(): ?int
    {
        return $this->id;
    }

    #[Groups(['task:list', 'task:read', 'task:write'])]
    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    #[Groups(['task:read', 'task:write'])]
    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    #[Groups(['task:list', 'task:read', 'task:write'])]
    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    #[Groups(['task:list', 'task:read', 'task:write'])]
    public function getPriority(): string
    {
        return $this->priority;
    }

    public function setPriority(string $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    #[Groups(['task:read', 'task:write'])]
    public function getDueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function setDueDate(?\DateTimeImmutable $dueDate): static
    {
        $this->dueDate = $dueDate;

        return $this;
    }

    #[Groups(['task:read'])]
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[Groups(['task:read'])]
    public function getProject(): ?Project
    {
        return $this->project;
    }

    public function setProject(?Project $project): static
    {
        $this->project = $project;

        return $this;
    }

    #[Groups(['task:read'])]
    public function getSprint(): ?Sprint
    {
        return $this->sprint;
    }

    public function setSprint(?Sprint $sprint): static
    {
        $this->sprint = $sprint;

        return $this;
    }

    #[Groups(['task:read'])]
    public function getAuthor(): ?User
    {
        return $this->author;
    }

    public function setAuthor(?User $author): static
    {
        $this->author = $author;

        return $this;
    }

    #[Groups(['task:list', 'task:read'])]
    public function getAssignee(): ?User
    {
        return $this->assignee;
    }

    public function setAssignee(?User $assignee): static
    {
        $this->assignee = $assignee;

        return $this;
    }

    public function getParent(): ?Task
    {
        return $this->parent;
    }

    public function setParent(?Task $parent): static
    {
        $this->parent = $parent;

        return $this;
    }

    /** @return Collection<int, Task> */
    public function getSubtasks(): Collection
    {
        return $this->subtasks;
    }

    public function addSubtask(Task $subtask): static
    {
        if (!$this->subtasks->contains($subtask)) {
            $this->subtasks->add($subtask);
            $subtask->setParent($this);
        }

        return $this;
    }

    public function removeSubtask(Task $subtask): static
    {
        if ($this->subtasks->removeElement($subtask) && $subtask->getParent() === $this) {
            $subtask->setParent(null);
        }

        return $this;
    }

    /** @return Collection<int, TaskComment> */
    public function getComments(): Collection
    {
        return $this->comments;
    }

    public function addComment(TaskComment $comment): static
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->setTask($this);
        }

        return $this;
    }

    public function removeComment(TaskComment $comment): static
    {
        if ($this->comments->removeElement($comment) && $comment->getTask() === $this) {
            $comment->setTask(null);
        }

        return $this;
    }

    /** @return Collection<int, TimeEntry> */
    public function getTimeEntries(): Collection
    {
        return $this->timeEntries;
    }

    public function addTimeEntry(TimeEntry $timeEntry): static
    {
        if (!$this->timeEntries->contains($timeEntry)) {
            $this->timeEntries->add($timeEntry);
            $timeEntry->setTask($this);
        }

        return $this;
    }

    public function removeTimeEntry(TimeEntry $timeEntry): static
    {
        if ($this->timeEntries->removeElement($timeEntry) && $timeEntry->getTask() === $this) {
            $timeEntry->setTask(null);
        }

        return $this;
    }

    /** @return Collection<int, Document> */
    public function getDocuments(): Collection
    {
        return $this->documents;
    }

    public function addDocument(Document $document): static
    {
        if (!$this->documents->contains($document)) {
            $this->documents->add($document);
            $document->setTask($this);
        }

        return $this;
    }

    public function removeDocument(Document $document): static
    {
        if ($this->documents->removeElement($document) && $document->getTask() === $this) {
            $document->setTask(null);
        }

        return $this;
    }

    /** @return Collection<int, Label> */
    public function getLabels(): Collection
    {
        return $this->labels;
    }

    public function addLabel(Label $label): static
    {
        if (!$this->labels->contains($label)) {
            $this->labels->add($label);
        }

        return $this;
    }

    public function removeLabel(Label $label): static
    {
        $this->labels->removeElement($label);

        return $this;
    }

    /** @return Collection<int, User> */
    public function getWatchers(): Collection
    {
        return $this->watchers;
    }

    public function addWatcher(User $watcher): static
    {
        if (!$this->watchers->contains($watcher)) {
            $this->watchers->add($watcher);
        }

        return $this;
    }

    public function removeWatcher(User $watcher): static
    {
        $this->watchers->removeElement($watcher);

        return $this;
    }
}
