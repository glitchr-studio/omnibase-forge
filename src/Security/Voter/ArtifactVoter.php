<?php

namespace Base\Forge\Security\Voter;

use Base\Entity\User;
use Base\Forge\Entity\Artifact;
use Base\Forge\Repository\LicenseRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Who may download an artifact: anyone when its software is free; else the
 * holder of a valid licence covering its release - and the staff.
 *
 * @extends Voter<string, Artifact>
 */
final class ArtifactVoter extends Voter
{
    public const DOWNLOAD = 'FORGE_DOWNLOAD';

    public function __construct(
        private readonly LicenseRepository $licenses,
        private readonly Security $security,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::DOWNLOAD === $attribute && $subject instanceof Artifact;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $software = $subject->getSoftware();
        $release = $subject->getRelease();
        if (!$software || !$release || !$release->isPublished() && !$this->security->isGranted('ROLE_STAFF')) {
            return false;
        }
        if ($software->isFree()) {
            return true;
        }

        $user = $token->getUser();
        if (!$user instanceof User) {
            return false;
        }
        if ($this->security->isGranted('ROLE_STAFF')) {
            return true;
        }

        foreach ($this->licenses->findValidFor($user, $software) as $license) {
            if ($license->covers($release)) {
                return true;
            }
        }

        return false;
    }
}
