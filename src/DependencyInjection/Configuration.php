<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\DependencyInjection;

use Monolog\Level;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('clever_age_process_ui');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $tb->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('security')
                ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('roles')->defaultValue(['ROLE_ADMIN'])->end(); // Roles displayed inside user edit form
        $rootNode
            ->children()
                ->arrayNode('logs')
                ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('store_in_database')->defaultValue(true)->end() // enable/disable store log in database (log_record table)
                        ->scalarNode('database_level')->defaultValue(Level::Debug->name)->end() // min log level to store log record in database
                        ->scalarNode('report_increment_level')->defaultValue(Level::Warning->name)->end() // min log level to increment process execution report
            ->end();
        $rootNode
            ->children()
                ->arrayNode('design')
                    ->addDefaultsIfNotSet()
                        ->children()
                        ->scalarNode('logo_path')->defaultValue('bundles/cleverageprocessui/logo.jpg')->end()
            ->end();

        return $tb;
    }
}
