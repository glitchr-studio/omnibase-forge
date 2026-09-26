<?php

namespace Base\Forge\Security\Voter;

use Base\Entity\User;
use Base\Forge\Repository\LicenseRepository;
use Base\Forge\Repository\ProjectRepository;
use Base\Forge\Repository\SoftwareRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * git/git-bundle's per-repository check (git.repository_attribute: GIT_VIEW):
 * the staff browse everything; a client their projects' repositories; the
 * source of a free piece of software is open to any signed-in visitor, that
 * of a licensed one to its licence holders. A repository nothing here
 * claims (declared only in git.repositories) stays the staff's.
 *
 * @extends Voter<string, string>
 */
final class GitRepositoryVoter extends Voter
{
    public const VIEW = 'GIT_VIEW';

    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly SoftwareRepository $software,
        private readonly LicenseRepository $licenses,
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::VIEW === $attribute && \is_string($subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        if ($this->security->isGranted('ROLE_STAFF')) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }

        $project = $this->projects->findOneByRepository($subject);
        if ($project && $project->getClient()?->getId() === $user->getId()) {
            return true;
        }

        $software = $this->software->findOneBy(['repository' => $subject]);
        if ($software && $software->isVisible()) {
            return $software->isFree() || [] !== $this->licenses->findValidFor($user, $software);
        }

        return false;
    }
}
