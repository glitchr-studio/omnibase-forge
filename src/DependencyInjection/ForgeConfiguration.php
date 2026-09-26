<?php

namespace Base\Forge\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ForgeConfiguration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('forge');

        $treeBuilder->getRootNode()
            ->children()
                ->scalarNode('storage_dir')->defaultValue('%kernel.project_dir%/var/storage/forge')
                    ->info('Where release archives are written. Never under public/: they are handed out by the download controller.')->end()
                ->scalarNode('repositories_dir')->defaultValue('%kernel.project_dir%/var/storage/repositories')
                    ->info('Where the repositories of projects and software that are not in git.repositories are cloned (<name>.git).')->end()
                ->scalarNode('accel_prefix')->defaultValue('/_protected/forge/')
                    ->info('The nginx `internal` location aliasing storage_dir (X-Accel-Redirect). Empty: PHP streams the file itself.')->end()
                ->integerNode('download_ttl')->min(30)->defaultValue(600)
                    ->info('How long a signed download link stays valid, in seconds.')->end()
                ->scalarNode('support_store')->defaultValue('support')
                    ->info('The market store (slug) hour packs and accepted quotes are sold from.')->end()
                ->scalarNode('software_store')->defaultValue('logiciels')
                    ->info('The market store (slug) licence offers are sold from.')->end()
                ->integerNode('hourly_rate')->min(0)->defaultValue(6000)
                    ->info('Default hourly rate of a quote line, in cents.')->end()
                ->integerNode('low_balance_minutes')->min(0)->defaultValue(120)
                    ->info('Below this balance a client is reminded to top up (forge:hours:low-balance).')->end()
                ->scalarNode('contact_email')->defaultNull()
                    ->info('Where quote requests are mailed. Null: they are only listed in the back office.')->end()
            ->end();

        return $treeBuilder;
    }
}
