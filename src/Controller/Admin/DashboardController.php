<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Entity\Task;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
final class DashboardController extends AbstractController
{
    private const STATUS_LABELS = [
        Task::STATUS_TODO => 'À faire',
        Task::STATUS_IN_PROGRESS => 'En cours',
        Task::STATUS_IN_REVIEW => 'En revue',
        Task::STATUS_DONE => 'Terminé',
    ];

    #[Route('', name: 'app_admin_dashboard', methods: ['GET'])]
    public function index(
        UserRepository $users,
        ProjectRepository $projects,
        TaskRepository $tasks,
        ChartBuilderInterface $chartBuilder,
    ): Response {
        $byStatus = $tasks->countByStatusGlobal();

        $tasksChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT)
            ->setData([
                'labels' => array_values(self::STATUS_LABELS),
                'datasets' => [[
                    'data' => array_map(static fn (string $s): int => $byStatus[$s] ?? 0, array_keys(self::STATUS_LABELS)),
                    'backgroundColor' => ['#9ca3af', '#3b82f6', '#f59e0b', '#22c55e'],
                ]],
            ])
            ->setOptions(['maintainAspectRatio' => false]);

        return $this->render('admin/dashboard.html.twig', [
            'stats' => [
                'usersTotal' => $users->countAll(),
                'usersActive' => $users->countActive(),
                'projectsActive' => $projects->countByStatus(Project::STATUS_ACTIVE),
                'projectsArchived' => $projects->countByStatus(Project::STATUS_ARCHIVED),
                'tasksTotal' => $tasks->countAll(),
            ],
            'tasksChart' => $tasksChart,
        ]);
    }
}
