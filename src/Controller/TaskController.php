<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Task;
use App\Entity\User;
use App\Form\DocumentType;
use App\Form\TaskCommentType;
use App\Form\TaskType;
use App\Form\TimeEntryType;
use App\Message\TaskAssignedMessage;
use App\Security\Voter\ProjectVoter;
use App\Security\Voter\TaskVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class TaskController extends AbstractController
{
    private const VALID_TYPES = ['bug', 'feature', 'story'];

    #[Route('/projects/{id}/tasks/new', name: 'app_task_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ProjectVoter::VIEW, subject: 'project')]
    public function new(Request $request, Project $project, EntityManagerInterface $em, MessageBusInterface $bus): Response
    {
        $type = $request->query->get('type', 'bug');
        if (!\in_array($type, self::VALID_TYPES, true)) {
            $type = 'bug';
        }

        $form = $this->createForm(TaskType::class, null, [
            'project' => $project,
            'default_type' => $type,
        ]);
        $form->handleRequest($request);

        // The dynamic-form Stimulus controller re-submits on type change: re-render the fields only.
        if ($request->isXmlHttpRequest()) {
            return $this->render('task/_form.html.twig', ['form' => $form, 'project' => $project]);
        }

        if ($form->isSubmitted() && $form->get('save')->isClicked() && $form->isValid()) {
            /** @var Task $task */
            $task = $form->getData();
            $task->setProject($project)->setAuthor($this->getUser());
            $em->persist($task);
            $em->flush();

            // Notification asynchrone si la tâche est assignée dès la création.
            if (null !== $task->getAssignee()) {
                $bus->dispatch(new TaskAssignedMessage($task->getId(), $task->getAssignee()->getId()));
            }
            $this->addFlash('success', 'Tâche créée.');

            return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
        }

        return $this->render('task/new.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/tasks/{id}', name: 'app_task_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(TaskVoter::VIEW, subject: 'task')]
    public function show(Task $task): Response
    {
        return $this->render('task/show.html.twig', [
            'task' => $task,
            'comment_form' => $this->createForm(TaskCommentType::class)->createView(),
            'worklog_form' => $this->createForm(TimeEntryType::class)->createView(),
            'document_form' => $this->createForm(DocumentType::class)->createView(),
        ]);
    }

    #[Route('/tasks/{id}/edit', name: 'app_task_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(TaskVoter::EDIT, subject: 'task')]
    public function edit(Request $request, Task $task, EntityManagerInterface $em, MessageBusInterface $bus): Response
    {
        $previousAssignee = $task->getAssignee(); // capturé avant liaison du formulaire

        $form = $this->createForm(TaskType::class, $task, [
            'project' => $task->getProject(),
            'default_type' => $task->getType(),
            'lock_type' => true, // a task's type is fixed once created (STI row identity)
        ]);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest()) {
            return $this->render('task/_form.html.twig', ['form' => $form, 'project' => $task->getProject()]);
        }

        if ($form->isSubmitted() && $form->get('save')->isClicked() && $form->isValid()) {
            $em->flush();

            // Notifier seulement si l'assigné a changé vers un utilisateur non nul.
            $assignee = $task->getAssignee();
            if (null !== $assignee && $assignee !== $previousAssignee) {
                $bus->dispatch(new TaskAssignedMessage($task->getId(), $assignee->getId()));
            }
            $this->addFlash('success', 'Tâche mise à jour.');

            return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
        }

        return $this->render('task/edit.html.twig', [
            'task' => $task,
            'form' => $form,
        ]);
    }

    #[Route('/tasks/{id}/move', name: 'app_task_move', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::EDIT, subject: 'task')]
    public function move(Request $request, Task $task, EntityManagerInterface $em): JsonResponse
    {
        $status = (string) $request->request->get('status');
        $allowed = [Task::STATUS_TODO, Task::STATUS_IN_PROGRESS, Task::STATUS_IN_REVIEW, Task::STATUS_DONE];

        if (!\in_array($status, $allowed, true)) {
            return $this->json(['ok' => false, 'error' => 'invalid status'], Response::HTTP_BAD_REQUEST);
        }
        if (!$this->isCsrfTokenValid('move_task_'.$task->getId(), (string) $request->request->get('_token'))) {
            return $this->json(['ok' => false, 'error' => 'invalid token'], Response::HTTP_FORBIDDEN);
        }

        $task->setStatus($status);
        $em->flush();

        return $this->json(['ok' => true, 'status' => $status]);
    }

    #[Route('/tasks/{id}/delete', name: 'app_task_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::DELETE, subject: 'task')]
    public function delete(Request $request, Task $task, EntityManagerInterface $em): Response
    {
        $projectId = $task->getProject()->getId();
        if ($this->isCsrfTokenValid('delete_task_'.$task->getId(), $request->request->getString('_token'))) {
            $em->remove($task);
            $em->flush();
            $this->addFlash('success', 'Tâche supprimée.');
        }

        return $this->redirectToRoute('app_project_board', ['id' => $projectId]);
    }
}
