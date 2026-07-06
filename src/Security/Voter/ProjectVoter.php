<?php

namespace App\Security\Voter;

use App\Entity\Project;
use App\Entity\ProjectMembership;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

final class ProjectVoter extends Voter
{
    public const VIEW = 'PROJECT_VIEW';
    public const EDIT = 'PROJECT_EDIT';
    public const DELETE = 'PROJECT_DELETE';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return in_array($attribute, [self::VIEW, self::EDIT, self::DELETE], true)
            && $subject instanceof Project;
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

        /** @var Project $project */
        $project = $subject;

        return match ($attribute) {
            // Voir : tout membre du projet
            self::VIEW => $this->isMember($user, $project),
            // Éditer / supprimer : chef de projet (lead) uniquement
            self::EDIT, self::DELETE => $this->isLead($user, $project),
            default => false,
        };
    }

    private function isMember(User $user, Project $project): bool
    {
        return $this->membership($user, $project) !== null;
    }

    private function isLead(User $user, Project $project): bool
    {
        return $this->membership($user, $project)?->getInternalRole() === ProjectMembership::ROLE_LEAD;
    }

    private function membership(User $user, Project $project): ?ProjectMembership
    {
        foreach ($project->getMemberships() as $membership) {
            if ($membership->getUser()?->getId() === $user->getId()) {
                return $membership;
            }
        }

        return null;
    }
}
