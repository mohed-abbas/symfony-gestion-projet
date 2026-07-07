<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\UserAdminType;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/admin/users')]
#[IsGranted('ROLE_ADMIN')]
final class UserAdminController extends AbstractController
{
    #[Route('', name: 'app_admin_user_index', methods: ['GET'])]
    public function index(UserRepository $users): Response
    {
        return $this->render('admin/user/index.html.twig', [
            'users' => $users->findBy([], ['createdAt' => 'DESC']),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_user_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, User $user, EntityManagerInterface $em): Response
    {
        // Empêche l'admin de se rétrograder / se désactiver lui-même (risque de lock-out).
        $isSelf = $user === $this->getUser();

        $form = $this->createForm(UserAdminType::class, $user, ['disabled' => $isSelf]);
        $form->handleRequest($request);

        if (!$isSelf && $form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'Utilisateur mis à jour.');

            return $this->redirectToRoute('app_admin_user_index');
        }

        return $this->render('admin/user/edit.html.twig', [
            'user' => $user,
            'form' => $form,
            'is_self' => $isSelf,
        ]);
    }
}
