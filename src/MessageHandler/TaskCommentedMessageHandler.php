<?php

namespace App\MessageHandler;

use App\Entity\Notification;
use App\Message\TaskCommentedMessage;
use App\Repository\TaskCommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

// Notifie l'assigné d'un nouveau commentaire, sauf s'il en est l'auteur.
#[AsMessageHandler]
final class TaskCommentedMessageHandler
{
    public function __construct(
        private readonly TaskCommentRepository $comments,
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(TaskCommentedMessage $message): void
    {
        $comment = $this->comments->find($message->commentId);
        if (null === $comment) {
            return;
        }

        $task = $comment->getTask();
        $recipient = $task?->getAssignee();
        // Pas de destinataire, ou l'auteur commente sa propre tâche → rien à notifier.
        if (null === $recipient || $recipient === $comment->getAuthor()) {
            return;
        }

        $notification = (new Notification())
            ->setUser($recipient)
            ->setType(Notification::TYPE_TASK_COMMENTED)
            ->setMessage(sprintf('Nouveau commentaire sur « %s ».', $task->getTitle()));
        $this->em->persist($notification);
        $this->em->flush();

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@taskflow.test', 'TaskFlow'))
            ->to((string) $recipient->getEmail())
            ->subject(sprintf('Nouveau commentaire : %s', $task->getTitle()))
            ->htmlTemplate('email/task_commented.html.twig')
            ->context(['comment' => $comment, 'task' => $task]);
        $this->mailer->send($email);
    }
}
