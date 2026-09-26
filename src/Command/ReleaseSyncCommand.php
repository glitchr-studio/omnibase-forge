<?php

namespace Base\Forge\Command;

use Base\Forge\Repository\SoftwareRepository;
use Base\Forge\Service\ReleaseSync;
use Git\Repository\RepositoryRegistry;
use Git\Warmer\RepositoryWarmer;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * New tags become releases: fetch each software's repository (when it has a
 * url), then create a release and its zip for every version tag not seen
 * yet. Run hourly by cron; `make releases` runs it by hand.
 */
#[AsCommand(name: 'forge:release:sync', description: 'Turn new version tags of the software repositories into releases')]
class ReleaseSyncCommand extends Command
{
    public function __construct(
        private readonly SoftwareRepository $software,
        private readonly ReleaseSync $sync,
        private readonly RepositoryRegistry $registry,
        private readonly RepositoryWarmer $warmer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('slugs', InputArgument::IS_ARRAY | InputArgument::OPTIONAL, 'Only this software')
            ->addOption('no-fetch', null, InputOption::VALUE_NONE, 'Do not clone/fetch the repositories first')
            ->addOption('no-build', null, InputOption::VALUE_NONE, 'Create the releases without building their archives');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $slugs = $input->getArgument('slugs');
        $failed = false;

        foreach ($this->software->findWithRepository() as $software) {
            if ($slugs && !\in_array($software->getSlug(), $slugs, true)) {
                continue;
            }

            $repository = (string) $software->getGitRepository();
            if (!$input->getOption('no-fetch') && ($config = $this->registry->get($repository))) {
                $this->warmer->sync($repository, $config);
            }

            try {
                $releases = $this->sync->sync($software, !$input->getOption('no-build'));
            } catch (\Throwable $e) {
                $io->error(sprintf('%s: %s', $software->getName(), $e->getMessage()));
                if ($output->isVeryVerbose()) {
                    $io->writeln($e->getTraceAsString());
                }
                $failed = true;
                continue;
            }

            foreach ($releases as $release) {
                $io->writeln(sprintf(' <info>+</info> %s %s (%s)', $software->getName(), $release->getVersion(), substr((string) $release->getCommitSha(), 0, 8)));
            }
            if (!$releases && $output->isVerbose()) {
                $io->writeln(sprintf(' <comment>=</comment> %s: up to date', $software->getName()));
            }
        }

        return $failed ? Command::FAILURE : Command::SUCCESS;
    }
}
