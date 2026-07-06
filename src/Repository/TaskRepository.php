<?php

namespace App\Repository;

use App\Entity\Project;
use App\Entity\StoryTask;
use App\Entity\Task;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Task>
 */
class TaskRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Task::class);
    }

    /**
     * Board tasks with assignee and sprint eager-loaded in one query.
     * Kills the N+1 the Twig board template would otherwise trigger per card.
     *
     * @return Task[]
     */
    public function findBoardForProject(Project $project): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.assignee', 'a')->addSelect('a')
            ->leftJoin('t.sprint', 's')->addSelect('s')
            ->andWhere('t.project = :project')->setParameter('project', $project)
            ->orderBy('t.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Open tasks past their due date, assignee eager-loaded.
     *
     * @return Task[]
     */
    public function findOverdue(Project $project): array
    {
        return $this->createQueryBuilder('t')
            ->leftJoin('t.assignee', 'a')->addSelect('a')
            ->andWhere('t.project = :project')->setParameter('project', $project)
            ->andWhere('t.dueDate < :today')->setParameter('today', new \DateTimeImmutable('today'))
            ->andWhere('t.status != :done')->setParameter('done', Task::STATUS_DONE)
            ->orderBy('t.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Task counts per status for a project (single GROUP BY query).
     *
     * @return array<string, int> status => count
     */
    public function countByStatus(Project $project): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select('t.status AS status', 'COUNT(t.id) AS total')
            ->andWhere('t.project = :project')->setParameter('project', $project)
            ->groupBy('t.status')
            ->getQuery()
            ->getScalarResult();

        return array_column($rows, 'total', 'status');
    }

    /**
     * Task counts per STI type. The discriminator isn't a mapped field, so we
     * count each subtype in one query via INSTANCE OF inside CASE.
     *
     * @return array<string, int> type => count
     */
    public function countByType(Project $project): array
    {
        $row = $this->createQueryBuilder('t')
            ->select(
                'SUM(CASE WHEN t INSTANCE OF App\Entity\BugTask THEN 1 ELSE 0 END) AS bug',
                'SUM(CASE WHEN t INSTANCE OF App\Entity\FeatureTask THEN 1 ELSE 0 END) AS feature',
                'SUM(CASE WHEN t INSTANCE OF App\Entity\StoryTask THEN 1 ELSE 0 END) AS story',
            )
            ->andWhere('t.project = :project')->setParameter('project', $project)
            ->getQuery()
            ->getSingleResult();

        return ['bug' => (int) $row['bug'], 'feature' => (int) $row['feature'], 'story' => (int) $row['story']];
    }

    /**
     * Open-task count per assignee (workload). Unassigned tasks are excluded.
     *
     * @return array<int, array{name: string, total: int}>
     */
    public function workloadByAssignee(Project $project): array
    {
        $rows = $this->createQueryBuilder('t')
            ->select("CONCAT(a.firstName, ' ', a.lastName) AS name", 'COUNT(t.id) AS total')
            ->innerJoin('t.assignee', 'a')
            ->andWhere('t.project = :project')->setParameter('project', $project)
            ->andWhere('t.status != :done')->setParameter('done', Task::STATUS_DONE)
            ->groupBy('a.id')->addGroupBy('a.firstName')->addGroupBy('a.lastName')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getResult();

        return array_map(static fn (array $r): array => [
            'name' => $r['name'],
            'total' => (int) $r['total'],
        ], $rows);
    }

    /**
     * Story-point totals: points delivered (done stories) vs. all stories.
     *
     * @return array{done: int, total: int}
     */
    public function storyPointStats(Project $project): array
    {
        // Query the StoryTask subtype directly so storyPoints is addressable.
        $row = $this->getEntityManager()->createQueryBuilder()
            ->select(
                'COALESCE(SUM(CASE WHEN st.status = :done THEN st.storyPoints ELSE 0 END), 0) AS done',
                'COALESCE(SUM(st.storyPoints), 0) AS total',
            )
            ->from(StoryTask::class, 'st')
            ->andWhere('st.project = :project')->setParameter('project', $project)
            ->setParameter('done', Task::STATUS_DONE)
            ->getQuery()
            ->getSingleResult();

        return ['done' => (int) $row['done'], 'total' => (int) $row['total']];
    }
}
