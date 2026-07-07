<?php

namespace App\Controller;

use App\Entity\Task;
use App\Entity\TimeEntry;
use App\Entity\User;
use App\Form\TimeEntryType;
use App\Security\Voter\TaskVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class TimeEntryController extends AbstractController
{
    #[Route('/tasks/{id}/worklog', name: 'app_worklog_new', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(TaskVoter::VIEW, subject: 'task')]
    public function new(Request $request, Task $task, EntityManagerInterface $em): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $entry = (new TimeEntry())->setTask($task)->setUser($user);
        $form = $this->createForm(TimeEntryType::class, $entry);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($entry);
            $em->flush();
            $this->addFlash('success', 'Temps ajouté.');
        }

        return $this->redirectToRoute('app_task_show', ['id' => $task->getId()]);
    }
}
