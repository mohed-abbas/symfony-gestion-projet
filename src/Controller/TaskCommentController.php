<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\TaskComment;
use App\Entity\User;
use App\Form\TaskCommentType;
use App\Message\TaskCommentedMessage;
use App\Security\Voter\TaskVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class TaskCommentController extends AbstractController
{
    #[Route('/tasks/{id}/comments', name: 'app_comment_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, subject: 'task')]
    public function new(Request $request, Task $task, EntityManagerInterface $em, MessageBusInterface $bus): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $comment = (new TaskComment())->setTask($task)->setAuthor($user);
        $form = $this->createForm(TaskCommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($comment);
            $em->flush();
            $bus->dispatch(new TaskCommentedMessage($comment->getId()));
            $this->addFlash('success', 'Commentaire ajouté.');
        }

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }

    #[Route('/comments/{id}/delete', name: 'app_comment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, TaskComment $comment, EntityManagerInterface $em): Response
    {
        $task = $comment->getTask();
        /** @var User $user */
        $user = $this->getUser();
        // Author, project lead or admin may remove a comment.
        $isAuthor = $comment->getAuthor()?->getId() === $user->getId();
        if (!$isAuthor && !$this->isGranted(TaskVoter::DELETE, $task)) {
            throw $this->createAccessDeniedException();
        }

        if ($this->isCsrfTokenValid('delete_comment_'.$comment->getId(), $request->request->getString('_token'))) {
            $em->remove($comment);
            $em->flush();
            $this->addFlash('success', 'Commentaire supprimé.');
        }

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }
}
