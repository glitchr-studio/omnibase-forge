<?php

namespace Base\Forge\Git;

use Base\Forge\Repository\ProjectRepository;
use Base\Forge\Repository\SoftwareRepository;
use Git\Repository\RepositoryProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Hands git/git-bundle the repositories of the projects and the software, so
 * a new client project is browsable (and clonable by git:sync) the moment it
 * is saved - no git.repositories line to add. Each lives at
 * forge.repositories_dir/<repository>.git; one also declared in
 * git.repositories keeps its configured path.
 */
final class ForgeRepositoryProvider implements RepositoryProviderInterface
{
    public function __construct(
        private readonly ProjectRepository $projects,
        private readonly SoftwareRepository $software,
        #[Autowire('%forge.repositories_dir%')] private readonly string $directory,
    ) {
    }

    public function getRepositories(): array
    {
        $repositories = [];

        foreach ($this->software->findWithRepository() as $software) {
            $repositories[$software->getRepository()] = $this->entry($software->getRepository(), $software->getRepositoryUrl(), $software->getName(), $software->getTagline());
        }
        foreach ($this->projects->findWithRepository() as $project) {
            $repositories[$project->getRepository()] = $this->entry($project->getRepository(), $project->getRepositoryUrl(), $project->getName(), $project->getSummary());
        }

        return $repositories;
    }

    private function entry(string $name, ?string $url, string $label, ?string $description): array
    {
        return [
            'path' => rtrim($this->directory, '/').'/'.$name.'.git',
            'url' => $url,
            'label' => $label,
            'description' => $description,
            'default_branch' => 'HEAD',
        ];
    }
}
