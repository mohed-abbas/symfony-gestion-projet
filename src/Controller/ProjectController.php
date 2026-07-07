<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\Task;
use App\Entity\User;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use App\Repository\TaskRepository;
use App\Security\Voter\ProjectVoter;
use App\Service\ProjectProgressCalculator;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\UX\Chartjs\Builder\ChartBuilderInterface;
use Symfony\UX\Chartjs\Model\Chart;

#[Route('/projects')]
#[IsGranted('ROLE_USER')]
final class ProjectController extends AbstractController
{
    #[Route('', name: 'app_project_index', methods: ['GET'])]
    public function index(ProjectRepository $projects): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('project/index.html.twig', [
            'projects' => $projects->findForUser($user),
        ]);
    }

    #[Route('/new', name: 'app_project_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_MANAGER')] // spec §2.2 : créer projets/organisations = chef de projet
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $project = new Project();
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var User $user */
            $user = $this->getUser();
            // Creator automatically becomes the project LEAD.
            $membership = (new ProjectMembership())
                ->setProject($project)
                ->setUser($user)
                ->setInternalRole(ProjectMembership::ROLE_LEAD);
            $project->addMembership($membership);

            $em->persist($project);
            $em->persist($membership);
            $em->flush();

            $this->addFlash('success', 'Projet créé.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/new.html.twig', ['form' => $form]);
    }

    #[Route('/{id}', name: 'app_project_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ProjectVoter::VIEW, subject: 'project')]
    public function show(Project $project): Response
    {
        return $this->render('project/show.html.twig', [
            'project' => $project,
            'statuses' => $this->statusLabels(),
        ]);
    }

    #[Route('/{id}/board', name: 'app_project_board', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ProjectVoter::VIEW, subject: 'project')]
    public function board(Project $project, TaskRepository $tasks): Response
    {
        $columns = [];
        foreach ($this->statusLabels() as $status => $label) {
            $columns[$status] = ['label' => $label, 'tasks' => []];
        }
        // Single query with assignee/sprint joined — no per-card N+1.
        foreach ($tasks->findBoardForProject($project) as $task) {
            $columns[$task->getStatus()]['tasks'][] = $task;
        }

        return $this->render('project/board.html.twig', [
            'project' => $project,
            'columns' => $columns,
        ]);
    }

    #[Route('/{id}/stats', name: 'app_project_stats', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ProjectVoter::VIEW, subject: 'project')]
    public function stats(
        Project $project,
        TaskRepository $tasks,
        ProjectProgressCalculator $progress,
        ChartBuilderInterface $chartBuilder,
    ): Response {
        $statusLabels = $this->statusLabels();
        $byStatus = $tasks->countByStatus($project);
        $byType = $tasks->countByType($project);
        $workload = $tasks->workloadByAssignee($project);

        // Doughnut: tasks per status, in Kanban order.
        $statusChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT)
            ->setData([
                'labels' => array_values($statusLabels),
                'datasets' => [[
                    'data' => array_map(static fn (string $s): int => $byStatus[$s] ?? 0, array_keys($statusLabels)),
                    'backgroundColor' => ['#9ca3af', '#3b82f6', '#f59e0b', '#22c55e'],
                ]],
            ])
            ->setOptions(['maintainAspectRatio' => false, 'plugins' => ['legend' => ['position' => 'bottom']]]);

        // Doughnut: tasks per STI type.
        $typeLabels = ['bug' => 'Bug', 'feature' => 'Fonctionnalité', 'story' => 'User story'];
        $typeChart = $chartBuilder->createChart(Chart::TYPE_DOUGHNUT)
            ->setData([
                'labels' => array_values($typeLabels),
                'datasets' => [[
                    'data' => array_map(static fn (string $t): int => $byType[$t] ?? 0, array_keys($typeLabels)),
                    'backgroundColor' => ['#ef4444', '#6366f1', '#10b981'],
                ]],
            ])
            ->setOptions(['maintainAspectRatio' => false, 'plugins' => ['legend' => ['position' => 'bottom']]]);

        // Bar: open tasks per assignee.
        $workloadChart = $chartBuilder->createChart(Chart::TYPE_BAR)
            ->setData([
                'labels' => array_column($workload, 'name'),
                'datasets' => [[
                    'label' => 'Tâches ouvertes',
                    'data' => array_column($workload, 'total'),
                    'backgroundColor' => '#6366f1',
                ]],
            ])
            ->setOptions([
                'maintainAspectRatio' => false,
                'plugins' => ['legend' => ['display' => false]],
                'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
            ]);

        return $this->render('project/stats.html.twig', [
            'project' => $project,
            'progress' => $progress->forProject($project),
            'statusLabels' => $statusLabels,
            'byStatus' => $byStatus,
            'overdue' => $tasks->findOverdue($project),
            'statusChart' => $statusChart,
            'typeChart' => $typeChart,
            'workloadChart' => $workloadChart,
            'hasWorkload' => $workload !== [],
        ]);
    }

    #[Route('/{id}/backlog', name: 'app_project_backlog', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(ProjectVoter::VIEW, subject: 'project')]
    public function backlog(Project $project): Response
    {
        // Tasks grouped by sprint; sprint-less tasks land in the backlog bucket.
        $bySprint = [];
        $backlog = [];
        foreach ($project->getTasks() as $task) {
            if ($sprint = $task->getSprint()) {
                $bySprint[$sprint->getId()]['sprint'] = $sprint;
                $bySprint[$sprint->getId()]['tasks'][] = $task;
            } else {
                $backlog[] = $task;
            }
        }

        return $this->render('project/backlog.html.twig', [
            'project' => $project,
            'bySprint' => $bySprint,
            'backlog' => $backlog,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_project_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ProjectVoter::EDIT, subject: 'project')]
    public function edit(Request $request, Project $project, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Projet mis à jour.');

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_project_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(ProjectVoter::DELETE, subject: 'project')]
    public function delete(Request $request, Project $project, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete_project_'.$project->getId(), $request->request->getString('_token'))) {
            $em->remove($project);
            $em->flush();
            $this->addFlash('success', 'Projet supprimé.');
        }

        return $this->redirectToRoute('app_project_index');
    }

    /** @return array<string, string> status => French label, in Kanban order */
    private function statusLabels(): array
    {
        return [
            Task::STATUS_TODO => 'À faire',
            Task::STATUS_IN_PROGRESS => 'En cours',
            Task::STATUS_IN_REVIEW => 'En revue',
            Task::STATUS_DONE => 'Terminé',
        ];
    }
}
