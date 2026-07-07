<?php

namespace App\MessageHandler;

use App\Entity\Notification;
use App\Message\ProjectMemberAddedMessage;
use App\Repository\ProjectRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

// Exécuté par le worker (transport async Doctrine) : notif in-app + e-mail d'invitation au projet.
#[AsMessageHandler]
final class ProjectMemberAddedMessageHandler
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(ProjectMemberAddedMessage $message): void
    {
        $project = $this->projects->find($message->projectId);
        $member = $this->users->find($message->memberId);
        // Le projet/l'utilisateur ont pu être supprimés entre l'envoi et le traitement.
        if (null === $project || null === $member) {
            return;
        }

        $notification = (new Notification())
            ->setUser($member)
            ->setType(Notification::TYPE_PROJECT_INVITED)
            ->setMessage(sprintf('Vous avez été ajouté au projet « %s ».', $project->getName()));
        $this->em->persist($notification);
        $this->em->flush();

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@taskflow.test', 'TaskFlow'))
            ->to((string) $member->getEmail())
            ->subject(sprintf('Vous avez rejoint le projet : %s', $project->getName()))
            ->htmlTemplate('email/project_member_added.html.twig')
            ->context(['project' => $project, 'member' => $member]);
        $this->mailer->send($email);
    }
}
