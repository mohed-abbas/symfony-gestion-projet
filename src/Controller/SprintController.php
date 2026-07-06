<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\Sprint;
use App\Form\SprintType;
use App\Security\Voter\ProjectVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class SprintController extends AbstractController
{
    #[Route('/projects/{id}/sprints/new', name: 'app_sprint_new', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(ProjectVoter::EDIT, subject: 'project')]
    public function new(Request $request, Project $project, EntityManagerInterface $em): Response
    {
        $sprint = (new Sprint())->setProject($project);
        $form = $this->createForm(SprintType::class, $sprint);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($sprint);
            $em->flush();
            $this->addFlash('success', 'Sprint créé.');

            return $this->redirectToRoute('app_project_backlog', ['id' => $project->getId()]);
        }

        return $this->render('sprint/new.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/sprints/{id}/edit', name: 'app_sprint_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Sprint $sprint, EntityManagerInterface $em): Response
    {
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $sprint->getProject());

        $form = $this->createForm(SprintType::class, $sprint);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Sprint mis à jour.');

            return $this->redirectToRoute('app_project_backlog', ['id' => $sprint->getProject()->getId()]);
        }

        return $this->render('sprint/edit.html.twig', [
            'sprint' => $sprint,
            'form' => $form,
        ]);
    }

    #[Route('/sprints/{id}/delete', name: 'app_sprint_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Sprint $sprint, EntityManagerInterface $em): Response
    {
        $project = $sprint->getProject();
        $this->denyAccessUnlessGranted(ProjectVoter::EDIT, $project);

        if ($this->isCsrfTokenValid('delete_sprint_'.$sprint->getId(), $request->request->getString('_token'))) {
            $em->remove($sprint);
            $em->flush();
            $this->addFlash('success', 'Sprint supprimé.');
        }

        return $this->redirectToRoute('app_project_backlog', ['id' => $project->getId()]);
    }
}
