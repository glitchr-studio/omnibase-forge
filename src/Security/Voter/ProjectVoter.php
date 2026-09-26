<?php

namespace Base\Forge\Security\Voter;

use Base\Entity\User;
use Base\Forge\Entity\Project;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * A project is its client's, and the studio's: the client sees it and moves
 * its cards; staff see every project.
 *
 * @extends Voter<string, Project>
 */
final class ProjectVoter extends Voter
{
    public const VIEW = 'FORGE_PROJECT_VIEW';
    public const EDIT_BOARD = 'FORGE_PROJECT_EDIT_BOARD';

    public function __construct(private readonly Security $security)
    {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::EDIT_BOARD], true) && $subject instanceof Project;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        return $this->security->isGranted('ROLE_STAFF') || $subject->getClient()?->getId() === $user->getId();
    }
}
