<?php

namespace App\Controller\Admin;

use App\Entity\Project;
use App\Form\ProjectType;
use App\Repository\ProjectRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/projects')]
#[IsGranted('ROLE_ADMIN')]
final class ProjectAdminController extends AbstractController
{
    #[Route('', name: 'app_admin_project_index', methods: ['GET'])]
    public function index(ProjectRepository $projects): Response
    {
        return $this->render('admin/project/index.html.twig', [
            'projects' => $projects->findAllForAdmin(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_project_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Project $project, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ProjectType::class, $project);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Projet mis à jour.');

            return $this->redirectToRoute('app_admin_project_index');
        }

        return $this->render('admin/project/edit.html.twig', [
            'project' => $project,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/archive', name: 'app_admin_project_archive', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function archive(Request $request, Project $project, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('archive_'.$project->getId(), $request->request->getString('_token'))) {
            // Bascule actif <-> archivé.
            $project->setStatus($project->isArchived() ? Project::STATUS_ACTIVE : Project::STATUS_ARCHIVED);
            $em->flush();
            $this->addFlash('success', $project->isArchived() ? 'Projet archivé.' : 'Projet réactivé.');
        }

        return $this->redirectToRoute('app_admin_project_index');
    }
}
