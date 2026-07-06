<?php

namespace App\Service;

use App\Entity\Project;
use App\Entity\Task;
use App\Repository\TaskRepository;

/**
 * Computes a project's completion percentage.
 * Arithmetic lives in pure methods (no DB) so it stays unit-testable.
 */
final class ProjectProgressCalculator
{
    public function __construct(private readonly TaskRepository $tasks)
    {
    }

    /**
     * Full progress snapshot pulled from the DB (optimized aggregate queries).
     *
     * @return array{byTasks: int, byPoints: int, counts: array<string, int>, points: array{done: int, total: int}}
     */
    public function forProject(Project $project): array
    {
        $counts = $this->tasks->countByStatus($project);
        $points = $this->tasks->storyPointStats($project);

        return [
            'byTasks' => $this->taskCompletion($counts),
            'byPoints' => $this->pointCompletion($points['done'], $points['total']),
            'counts' => $counts,
            'points' => $points,
        ];
    }

    /**
     * Share of tasks marked done, as a whole percentage (0–100).
     *
     * @param array<string, int> $countByStatus status => count
     */
    public function taskCompletion(array $countByStatus): int
    {
        $total = array_sum($countByStatus);
        if ($total === 0) {
            return 0;
        }

        return (int) round(($countByStatus[Task::STATUS_DONE] ?? 0) / $total * 100);
    }

    /** Share of story points delivered, as a whole percentage (0–100). */
    public function pointCompletion(int $done, int $total): int
    {
        if ($total <= 0) {
            return 0;
        }

        return (int) round($done / $total * 100);
    }
}
