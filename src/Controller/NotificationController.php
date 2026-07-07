<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\NotificationRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'app_notification_index', methods: ['GET'])]
    public function index(NotificationRepository $notifications): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications->findForUser($user),
        ]);
    }

    #[Route('/notifications/read', name: 'app_notification_read', methods: ['POST'])]
    public function markAllRead(Request $request, NotificationRepository $notifications): Response
    {
        if ($this->isCsrfTokenValid('mark_read', $request->request->getString('_token'))) {
            /** @var User $user */
            $user = $this->getUser();
            $notifications->markAllRead($user);
        }

        return $this->redirectToRoute('app_notification_index');
    }

    // Fragment embarqué dans le layout (render(controller(...))) pour le compteur non lu.
    public function badge(NotificationRepository $notifications): Response
    {
        /** @var User $user */
        $user = $this->getUser();

        return $this->render('notification/_badge.html.twig', [
            'unread' => $notifications->countUnread($user),
        ]);
    }
}
