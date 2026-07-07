<?php

namespace App\Controller;

use App\Entity\BugTask;
use App\Entity\FeatureTask;
use App\Entity\Project;
use App\Entity\StoryTask;
use App\Entity\Task;
use App\Security\Voter\ProjectVoter;
use App\Service\AiTaskGenerator;
use App\Service\AiUnavailableException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/projects/{id}/ai', requirements: ['id' => '\d+'])]
#[IsGranted(ProjectVoter::EDIT, subject: 'project')]
final class AiTaskController extends AbstractController
{
    private const TYPE_MAP = [
        'bug' => BugTask::class,
        'feature' => FeatureTask::class,
        'story' => StoryTask::class,
    ];

    // Interroge l'IA puis stocke les suggestions en session (PRG) avant la page d'aperçu.
    #[Route('/generate', name: 'app_project_ai_generate', methods: ['POST'])]
    public function generate(Request $request, Project $project, AiTaskGenerator $generator): Response
    {
        if (!$this->isCsrfTokenValid('ai_generate_'.$project->getId(), $request->request->getString('_token'))) {
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        try {
            $suggestions = $generator->suggest($project);
        } catch (AiUnavailableException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        if ([] === $suggestions) {
            $this->addFlash('error', "L'IA n'a proposé aucune tâche pour ce projet.");

            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $request->getSession()->set($this->sessionKey($project), $suggestions);

        return $this->redirectToRoute('app_project_ai_preview', ['id' => $project->getId()]);
    }

    #[Route('/preview', name: 'app_project_ai_preview', methods: ['GET'])]
    public function preview(Request $request, Project $project): Response
    {
        $suggestions = $request->getSession()->get($this->sessionKey($project), []);
        if ([] === $suggestions) {
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        return $this->render('ai/preview.html.twig', [
            'project' => $project,
            'suggestions' => $suggestions,
        ]);
    }

    #[Route('/create', name: 'app_project_ai_create', methods: ['POST'])]
    public function create(Request $request, Project $project, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('ai_create_'.$project->getId(), $request->request->getString('_token'))) {
            return $this->redirectToRoute('app_project_show', ['id' => $project->getId()]);
        }

        $suggestions = $request->getSession()->get($this->sessionKey($project), []);
        $selected = $request->request->all('selected'); // indices cochés dans l'aperçu

        $created = 0;
        foreach ($selected as $index) {
            $data = $suggestions[$index] ?? null;
            if (null === $data) {
                continue;
            }

            $class = self::TYPE_MAP[$data['type']] ?? FeatureTask::class;
            /** @var Task $task */
            $task = new $class();
            $task->setTitle($data['title'])
                ->setDescription($data['description'])
                ->setPriority($data['priority'])
                ->setProject($project)
                ->setAuthor($this->getUser());
            $em->persist($task);
            ++$created;
        }

        if ($created > 0) {
            $em->flush();
        }
        $request->getSession()->remove($this->sessionKey($project));
        $this->addFlash('success', sprintf('%d tâche(s) créée(s) depuis les suggestions IA.', $created));

        return $this->redirectToRoute('app_project_board', ['id' => $project->getId()]);
    }

    private function sessionKey(Project $project): string
    {
        return 'ai_suggestions_'.$project->getId();
    }
}
