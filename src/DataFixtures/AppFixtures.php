<?php

namespace App\DataFixtures;

use App\Entity\ActivityLog;
use App\Entity\BugTask;
use App\Entity\Document;
use App\Entity\FeatureTask;
use App\Entity\Label;
use App\Entity\Notification;
use App\Entity\Organization;
use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\Sprint;
use App\Entity\StoryTask;
use App\Entity\Task;
use App\Entity\TaskComment;
use App\Entity\TimeEntry;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private Generator $faker;
    private string $sharedHash;

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // Seed fixe : jeu de données reproductible d'un chargement à l'autre
        $this->faker = Factory::create('fr_FR');
        $this->faker->seed(2026);

        $users = $this->loadUsers($manager);
        $labels = $this->loadLabels($manager);
        $organizations = $this->loadOrganizations($manager);
        $this->loadProjects($manager, $organizations, $users, $labels);

        $manager->flush();
    }

    /** @return User[] */
    private function loadUsers(ObjectManager $manager): array
    {
        // Hash calculé une seule fois (tous les comptes ont le mot de passe « password »)
        $probe = new User();
        $this->sharedHash = $this->hasher->hashPassword($probe, 'password');

        $users = [];

        // 3 comptes de test déterministes (documentés dans le cahier des charges)
        $accounts = [
            ['admin@taskflow.test', ['ROLE_ADMIN'], 'Alice', 'Admin'],
            ['manager@taskflow.test', ['ROLE_MANAGER'], 'Marc', 'Manager'],
            ['member@taskflow.test', ['ROLE_USER'], 'Mina', 'Membre'],
        ];
        foreach ($accounts as [$email, $roles, $first, $last]) {
            $users[] = $this->makeUser($manager, $email, $roles, $first, $last);
        }

        // Utilisateurs aléatoires supplémentaires
        for ($i = 0; $i < 15; ++$i) {
            $users[] = $this->makeUser(
                $manager,
                $this->faker->unique()->safeEmail(),
                ['ROLE_USER'],
                $this->faker->firstName(),
                $this->faker->lastName(),
            );
        }

        return $users;
    }

    private function makeUser(ObjectManager $manager, string $email, array $roles, string $first, string $last): User
    {
        $user = new User();
        $user->setEmail($email);
        $user->setRoles($roles);
        $user->setPassword($this->sharedHash);
        $user->setFirstName($first);
        $user->setLastName($last);
        $user->setBio($this->faker->optional(0.6)->sentence(10));
        $user->setIsVerified(true);
        $user->setCreatedAt($this->imm($this->faker->dateTimeBetween('-1 year', '-2 months')));
        $manager->persist($user);

        return $user;
    }

    /** @return Label[] */
    private function loadLabels(ObjectManager $manager): array
    {
        $palette = [
            ['Backend', '#2563eb'],
            ['Frontend', '#7c3aed'],
            ['Urgent', '#dc2626'],
            ['Documentation', '#0891b2'],
            ['UX', '#db2777'],
            ['Dette technique', '#ca8a04'],
            ['Bloquant', '#b91c1c'],
        ];
        $labels = [];
        foreach ($palette as [$name, $color]) {
            $label = new Label();
            $label->setName($name);
            $label->setColor($color);
            $manager->persist($label);
            $labels[] = $label;
        }

        return $labels;
    }

    /** @return Organization[] */
    private function loadOrganizations(ObjectManager $manager): array
    {
        $names = ['Acme Corp', 'Globex', 'Initech'];
        $organizations = [];
        foreach ($names as $name) {
            $org = new Organization();
            $org->setName($name);
            $org->setSlug($this->faker->unique()->slug(2));
            $org->setCreatedAt($this->imm($this->faker->dateTimeBetween('-1 year', '-6 months')));
            $manager->persist($org);
            $organizations[] = $org;
        }

        return $organizations;
    }

    /**
     * @param Organization[] $organizations
     * @param User[]         $users
     * @param Label[]        $labels
     */
    private function loadProjects(ObjectManager $manager, array $organizations, array $users, array $labels): void
    {
        $projectNames = [
            'Refonte du portail client', 'Application mobile', 'Migration infrastructure',
            'Plateforme e-commerce', 'Tableau de bord analytique', 'API publique v2',
            'Programme de fidélité', 'Outil interne RH',
        ];

        foreach ($projectNames as $index => $name) {
            $project = new Project();
            $project->setOrganization($this->faker->randomElement($organizations));
            $project->setName($name);
            $project->setProjectKey(strtoupper(substr(str_replace(' ', '', $name), 0, 4)).($index + 1));
            $project->setDescription($this->faker->paragraph());
            $start = $this->faker->dateTimeBetween('-8 months', '-1 month');
            $project->setStartDate($this->imm($start));
            $project->setEndDate($this->imm($this->faker->dateTimeBetween($start, '+4 months')));
            $project->setStatus($index % 5 === 0 ? Project::STATUS_ARCHIVED : Project::STATUS_ACTIVE);
            $project->setCreatedAt($this->imm($start));
            $manager->persist($project);

            $members = $this->loadMemberships($manager, $project, $users, $index);
            $sprints = $this->loadSprints($manager, $project);
            $this->loadTasks($manager, $project, $members, $sprints, $labels);
            $this->loadActivity($manager, $project, $members);
        }
    }

    /**
     * @param User[] $users
     *
     * @return User[] les membres du projet (le premier est le LEAD)
     */
    private function loadMemberships(ObjectManager $manager, Project $project, array $users, int $index): array
    {
        // Les 3 comptes de test sont membres des premiers projets pour une démo parlante
        $pool = $users;
        shuffle($pool);
        $members = array_slice($pool, 0, random_int(4, 7));

        // manager@ = LEAD des projets 0 et 1 ; member@ = contributeur ; admin@ membre du projet 0
        if ($index === 0) {
            $members = $this->ensureMembers($members, [$users[1], $users[2], $users[0]]);
            $lead = $users[1];
        } elseif ($index === 1) {
            $members = $this->ensureMembers($members, [$users[1], $users[2]]);
            $lead = $users[1];
        } else {
            $lead = $members[0];
        }

        foreach ($members as $user) {
            $role = match (true) {
                $user === $lead => ProjectMembership::ROLE_LEAD,
                $this->faker->boolean(70) => ProjectMembership::ROLE_CONTRIBUTOR,
                default => ProjectMembership::ROLE_VIEWER,
            };
            $membership = new ProjectMembership();
            $membership->setUser($user);
            $membership->setProject($project);
            $membership->setInternalRole($role);
            $membership->setJoinedAt($this->imm($this->faker->dateTimeBetween('-6 months', '-1 week')));
            $manager->persist($membership);
        }

        // LEAD en tête (doublon éventuel sans impact : sert au tirage aléatoire auteur/assigné)
        return array_merge([$lead], $members);
    }

    /**
     * @param User[] $members
     * @param User[] $required
     *
     * @return User[]
     */
    private function ensureMembers(array $members, array $required): array
    {
        foreach ($required as $user) {
            if (!in_array($user, $members, true)) {
                $members[] = $user;
            }
        }

        return $members;
    }

    /** @return Sprint[] */
    private function loadSprints(ObjectManager $manager, Project $project): array
    {
        $sprints = [];
        $count = random_int(2, 3);
        $cursor = $project->getStartDate();
        for ($i = 1; $i <= $count; ++$i) {
            $end = $cursor->modify('+2 weeks');
            $sprint = new Sprint();
            $sprint->setProject($project);
            $sprint->setName(sprintf('Sprint %d', $i));
            $sprint->setGoal($this->faker->sentence(8));
            $sprint->setStartDate($cursor);
            $sprint->setEndDate($end);
            $manager->persist($sprint);
            $sprints[] = $sprint;
            $cursor = $end;
        }

        return $sprints;
    }

    /**
     * @param User[]   $members
     * @param Sprint[] $sprints
     * @param Label[]  $labels
     */
    private function loadTasks(ObjectManager $manager, Project $project, array $members, array $sprints, array $labels): void
    {
        $statuses = [Task::STATUS_TODO, Task::STATUS_IN_PROGRESS, Task::STATUS_IN_REVIEW, Task::STATUS_DONE];
        $priorities = [Task::PRIORITY_LOW, Task::PRIORITY_MEDIUM, Task::PRIORITY_HIGH];

        /** @var Task[] $tasks */
        $tasks = [];
        $count = random_int(8, 12);
        for ($i = 0; $i < $count; ++$i) {
            $task = $this->makeTypedTask();
            $task->setTitle(rtrim($this->faker->sentence(random_int(4, 8)), '.'));
            $task->setDescription($this->faker->optional(0.8)->paragraph());
            $task->setStatus($this->faker->randomElement($statuses));
            $task->setPriority($this->faker->randomElement($priorities));
            $task->setProject($project);
            $task->setAuthor($this->faker->randomElement($members));
            if ($this->faker->boolean(75)) {
                $task->setAssignee($this->faker->randomElement($members));
            }
            if ($sprints && $this->faker->boolean(70)) {
                $task->setSprint($this->faker->randomElement($sprints));
            }
            if ($this->faker->boolean(60)) {
                $task->setDueDate($this->imm($this->faker->dateTimeBetween('-2 weeks', '+6 weeks')));
            }
            $task->setCreatedAt($this->dtBetween($project->getStartDate(), 'now'));

            // Labels (M2M) : 0 à 3 par tâche
            foreach ($this->faker->randomElements($labels, random_int(0, 3)) as $label) {
                $task->addLabel($label);
            }
            // Observateurs (M2M) : 0 à 3 membres
            foreach ($this->faker->randomElements($members, random_int(0, min(3, count($members)))) as $watcher) {
                $task->addWatcher($watcher);
            }

            $manager->persist($task);
            $tasks[] = $task;

            $this->loadComments($manager, $task, $members);
            $this->loadTimeEntries($manager, $task, $members);
            $this->loadDocuments($manager, $task, $members);
            $this->loadNotifications($manager, $task);
        }

        // Quelques sous-tâches : rattache 0-2 tâches à un parent du même projet
        foreach ($this->faker->randomElements($tasks, random_int(0, 2)) as $child) {
            $parent = $this->faker->randomElement($tasks);
            if ($parent !== $child && $child->getParent() === null) {
                $child->setParent($parent);
            }
        }
    }

    private function makeTypedTask(): Task
    {
        return match ($this->faker->randomElement(['bug', 'feature', 'story'])) {
            'bug' => (new BugTask())
                ->setSeverity($this->faker->randomElement([BugTask::SEVERITY_BLOCKER, BugTask::SEVERITY_MAJOR, BugTask::SEVERITY_MINOR]))
                ->setStepsToReproduce($this->faker->optional(0.7)->paragraph()),
            'feature' => (new FeatureTask())
                ->setBusinessValue($this->faker->optional(0.7)->sentence(12)),
            default => (new StoryTask())
                ->setStoryPoints($this->faker->randomElement([1, 2, 3, 5, 8, 13])),
        };
    }

    /** @param User[] $members */
    private function loadComments(ObjectManager $manager, Task $task, array $members): void
    {
        for ($i = 0; $i < random_int(0, 4); ++$i) {
            $comment = new TaskComment();
            $comment->setTask($task);
            $comment->setAuthor($this->faker->randomElement($members));
            $comment->setBody($this->faker->paragraph());
            $comment->setCreatedAt($this->dtBetween($task->getCreatedAt(), 'now'));
            $manager->persist($comment);
        }
    }

    /** @param User[] $members */
    private function loadTimeEntries(ObjectManager $manager, Task $task, array $members): void
    {
        for ($i = 0; $i < random_int(0, 3); ++$i) {
            $entry = new TimeEntry();
            $entry->setTask($task);
            $entry->setUser($this->faker->randomElement($members));
            $entry->setMinutes($this->faker->randomElement([15, 30, 45, 60, 90, 120, 240]));
            $entry->setSpentOn($this->dtBetween($task->getCreatedAt(), 'now'));
            $entry->setDescription($this->faker->optional(0.5)->sentence());
            $manager->persist($entry);
        }
    }

    /** @param User[] $members */
    private function loadDocuments(ObjectManager $manager, Task $task, array $members): void
    {
        if (!$this->faker->boolean(30)) {
            return;
        }
        $ext = $this->faker->randomElement(['pdf', 'png', 'docx', 'xlsx']);
        $mimes = ['pdf' => 'application/pdf', 'png' => 'image/png', 'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
        $doc = new Document();
        $doc->setTask($task);
        $doc->setOwner($this->faker->randomElement($members));
        $doc->setFilename($this->faker->slug(3).'.'.$ext);
        $doc->setMimeType($mimes[$ext]);
        $doc->setSize($this->faker->numberBetween(20_000, 5_000_000));
        $doc->setUploadedAt($this->dtBetween($task->getCreatedAt(), 'now'));
        $manager->persist($doc);
    }

    private function loadNotifications(ObjectManager $manager, Task $task): void
    {
        $assignee = $task->getAssignee();
        if ($assignee === null || !$this->faker->boolean(50)) {
            return;
        }
        $notif = new Notification();
        $notif->setUser($assignee);
        $notif->setType(Notification::TYPE_TASK_ASSIGNED);
        $notif->setMessage(sprintf('La tâche « %s » vous a été assignée.', $task->getTitle()));
        $notif->setIsRead($this->faker->boolean(40));
        $notif->setCreatedAt($this->dtBetween($task->getCreatedAt(), 'now'));
        $manager->persist($notif);
    }

    /** @param User[] $members */
    private function loadActivity(ObjectManager $manager, Project $project, array $members): void
    {
        $actions = [
            'a créé le projet', 'a ajouté un membre', 'a clôturé un sprint',
            'a déplacé une tâche en revue', 'a archivé une tâche',
        ];
        for ($i = 0; $i < random_int(3, 6); ++$i) {
            $log = new ActivityLog();
            $log->setProject($project);
            $log->setUser($this->faker->boolean(85) ? $this->faker->randomElement($members) : null);
            $log->setAction($this->faker->randomElement($actions));
            $log->setCreatedAt($this->dtBetween($project->getStartDate(), 'now'));
            $manager->persist($log);
        }
    }

    private function imm(\DateTime $date): \DateTimeImmutable
    {
        return \DateTimeImmutable::createFromMutable($date);
    }

    // Faker::dateTimeBetween n'accepte que \DateTime|string, pas \DateTimeImmutable
    private function dtBetween(string|\DateTimeInterface $start, string|\DateTimeInterface $end = 'now'): \DateTimeImmutable
    {
        $s = $start instanceof \DateTimeInterface ? $start->format('Y-m-d H:i:s') : $start;
        $e = $end instanceof \DateTimeInterface ? $end->format('Y-m-d H:i:s') : $end;

        return $this->imm($this->faker->dateTimeBetween($s, $e));
    }
}
