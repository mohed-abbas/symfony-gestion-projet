<?php

namespace App\MessageHandler;

use App\Entity\Notification;
use App\Message\TaskAssignedMessage;
use App\Repository\TaskRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Mime\Address;

// Exécuté par le worker (transport async Doctrine) : notif in-app + e-mail d'assignation.
#[AsMessageHandler]
final class TaskAssignedMessageHandler
{
    public function __construct(
        private readonly TaskRepository $tasks,
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function __invoke(TaskAssignedMessage $message): void
    {
        $task = $this->tasks->find($message->taskId);
        $assignee = $this->users->find($message->assigneeId);
        // La tâche/l'utilisateur ont pu être supprimés entre l'envoi et le traitement.
        if (null === $task || null === $assignee) {
            return;
        }

        $notification = (new Notification())
            ->setUser($assignee)
            ->setType(Notification::TYPE_TASK_ASSIGNED)
            ->setMessage(sprintf('La tâche « %s » vous a été assignée.', $task->getTitle()));
        $this->em->persist($notification);
        $this->em->flush();

        $email = (new TemplatedEmail())
            ->from(new Address('no-reply@taskflow.test', 'TaskFlow'))
            ->to((string) $assignee->getEmail())
            ->subject(sprintf('Nouvelle tâche assignée : %s', $task->getTitle()))
            ->htmlTemplate('email/task_assigned.html.twig')
            ->context(['task' => $task, 'assignee' => $assignee]);
        $this->mailer->send($email);
    }
}
