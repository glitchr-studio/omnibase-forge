<?php

namespace Base\Forge\Command;

use Base\Forge\Service\LicenseToken;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * A new Ed25519 pair for the activation tokens: the secret goes in the
 * vault (FORGE_LICENSE_SIGNING_KEY), the public key in the software. A new
 * pair makes every token issued so far unverifiable: the software asks
 * again, online.
 */
#[AsCommand('forge:license:keypair', 'Make the key pair that signs the licence activation tokens')]
class LicenseKeypairCommand extends Command
{
    public function __construct(private readonly LicenseToken $tokens)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $pair = LicenseToken::keypair();

        if ($this->tokens->isConfigured()) {
            $io->warning('A key is already set. Replacing it makes the software verify with the new public key: ship it before switching.');
        }
        $io->section('Secret key - in the vault, never in the repository:');
        $io->writeln('  bin/console secrets:set FORGE_LICENSE_SIGNING_KEY');
        $io->writeln('  '.$pair['secret']);
        $io->section('Public key - in the software (Unity, Switch for Coder...):');
        $io->writeln('  '.$pair['public']);

        return Command::SUCCESS;
    }
}
