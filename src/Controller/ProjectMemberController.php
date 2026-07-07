<?php

namespace App\Controller;

use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use App\Repository\UserRepository;
use App\Security\Voter\ProjectVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

// Gestion des membres d'un projet (spec §2.2 : inviter / retirer / définir le rôle interne).
// Réservé au chef de projet (ProjectVoter::EDIT = lead ou admin).
#[Route('/projects/{id}/members', requirements: ['id' => '\d+'])]
#[IsGranted(ProjectVoter::EDIT, subject: 'project')]
final class ProjectMemberController extends AbstractController
{
    private const ROLES = [
        ProjectMembership::ROLE_LEAD,
        ProjectMembership::ROLE_CONTRIBUTOR,
        ProjectMembership::ROLE_VIEWER,
    ];

    #[Route('', name: 'app_project_member_index', methods: ['GET'])]
    public function index(Project $project): Response
    {
        return $this->render('project/members.html.twig', [
            'project' => $project,
            'roles' => self::ROLES,
        ]);
    }

    #[Route('/add', name: 'app_project_member_add', methods: ['POST'])]
    public function add(Project $project, Request $request, UserRepository $users, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('member_add_'.$project->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $email = trim($request->request->getString('email'));
        $role = $request->request->getString('role');
        if (!in_array($role, self::ROLES, true)) {
            $role = ProjectMembership::ROLE_CONTRIBUTOR;
        }

        $user = $users->findOneBy(['email' => $email]);
        if ($user === null) {
            $this->addFlash('error', sprintf('Aucun utilisateur avec l’adresse « %s ».', $email));
        } elseif ($this->isMember($project, $user)) {
            $this->addFlash('warning', 'Cet utilisateur est déjà membre du projet.');
        } else {
            $membership = (new ProjectMembership())
                ->setProject($project)
                ->setUser($user)
                ->setInternalRole($role);
            $project->addMembership($membership);
            $em->persist($membership);
            $em->flush();
            $this->addFlash('success', sprintf('%s a été ajouté au projet.', $user->getFullName()));
        }

        return $this->redirectToRoute('app_project_member_index', ['id' => $project->getId()]);
    }

    #[Route('/{membership}/role', name: 'app_project_member_role', requirements: ['membership' => '\d+'], methods: ['POST'])]
    public function updateRole(Project $project, ProjectMembership $membership, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertBelongs($project, $membership);
        if (!$this->isCsrfTokenValid('member_role_'.$membership->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $role = $request->request->getString('role');
        if (!in_array($role, self::ROLES, true)) {
            $this->addFlash('error', 'Rôle invalide.');

            return $this->redirectToRoute('app_project_member_index', ['id' => $project->getId()]);
        }

        // Ne pas rétrograder le dernier chef : le projet doit toujours avoir un lead.
        if ($membership->getInternalRole() === ProjectMembership::ROLE_LEAD
            && $role !== ProjectMembership::ROLE_LEAD
            && $this->leadCount($project) <= 1) {
            $this->addFlash('error', 'Le projet doit conserver au moins un chef.');

            return $this->redirectToRoute('app_project_member_index', ['id' => $project->getId()]);
        }

        $membership->setInternalRole($role);
        $em->flush();
        $this->addFlash('success', 'Rôle mis à jour.');

        return $this->redirectToRoute('app_project_member_index', ['id' => $project->getId()]);
    }

    #[Route('/{membership}/remove', name: 'app_project_member_remove', requirements: ['membership' => '\d+'], methods: ['POST'])]
    public function remove(Project $project, ProjectMembership $membership, Request $request, EntityManagerInterface $em): Response
    {
        $this->assertBelongs($project, $membership);
        if (!$this->isCsrfTokenValid('member_remove_'.$membership->getId(), $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        if ($membership->getInternalRole() === ProjectMembership::ROLE_LEAD && $this->leadCount($project) <= 1) {
            $this->addFlash('error', 'Impossible de retirer le dernier chef du projet.');

            return $this->redirectToRoute('app_project_member_index', ['id' => $project->getId()]);
        }

        $project->removeMembership($membership);
        $em->remove($membership);
        $em->flush();
        $this->addFlash('success', 'Membre retiré du projet.');

        return $this->redirectToRoute('app_project_member_index', ['id' => $project->getId()]);
    }

    private function assertBelongs(Project $project, ProjectMembership $membership): void
    {
        if ($membership->getProject()?->getId() !== $project->getId()) {
            throw $this->createNotFoundException();
        }
    }

    private function isMember(Project $project, User $user): bool
    {
        foreach ($project->getMemberships() as $m) {
            if ($m->getUser()?->getId() === $user->getId()) {
                return true;
            }
        }

        return false;
    }

    private function leadCount(Project $project): int
    {
        $count = 0;
        foreach ($project->getMemberships() as $m) {
            if ($m->getInternalRole() === ProjectMembership::ROLE_LEAD) {
                ++$count;
            }
        }

        return $count;
    }
}
