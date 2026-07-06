<?php

namespace App\Security\Voter;

use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\Task;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class TaskVoter extends Voter
{
    public const VIEW = 'TASK_VIEW';
    public const EDIT = 'TASK_EDIT';
    public const DELETE = 'TASK_DELETE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof Task;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        // Les administrateurs ont tous les droits
        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        /** @var Task $task */
        $task = $subject;
        $project = $task->getProject();

        return match ($attribute) {
            // Voir : tout membre du projet (lecture ouverte à l'équipe)
            self::VIEW => $this->isProjectMember($user, $project),
            // Éditer : auteur, assigné, ou chef de projet (lead)
            self::EDIT => $this->isSameUser($user, $task->getAuthor())
                || $this->isSameUser($user, $task->getAssignee())
                || $this->isProjectLead($user, $project),
            // Supprimer : auteur ou chef de projet uniquement
            self::DELETE => $this->isSameUser($user, $task->getAuthor())
                || $this->isProjectLead($user, $project),
            default => false,
        };
    }

    private function isSameUser(User $user, ?User $other): bool
    {
        return $other !== null && $other->getId() === $user->getId();
    }

    private function isProjectMember(User $user, ?Project $project): bool
    {
        return $this->membership($user, $project) !== null;
    }

    private function isProjectLead(User $user, ?Project $project): bool
    {
        return $this->membership($user, $project)?->getInternalRole() === ProjectMembership::ROLE_LEAD;
    }

    private function membership(User $user, ?Project $project): ?ProjectMembership
    {
        if ($project === null) {
            return null;
        }
        foreach ($project->getMemberships() as $membership) {
            if ($membership->getUser()?->getId() === $user->getId()) {
                return $membership;
            }
        }

        return null;
    }
}
