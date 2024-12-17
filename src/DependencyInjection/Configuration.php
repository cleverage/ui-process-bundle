<?php

declare(strict_types=1);

/*
 * This file is part of the CleverAge/UiProcessBundle package.
 *
 * Copyright (c) Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\UiProcessBundle\DependencyInjection;

use Monolog\Level;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $tb = new TreeBuilder('clever_age_ui_process');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $tb->getRootNode();
        $rootNode
            ->children()
                ->arrayNode('security')
                ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('roles')->defaultValue(['ROLE_ADMIN'])->scalarPrototype()->end(); // Roles displayed inside user edit form
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
                        ->scalarNode('logo_path')->defaultValue('bundles/cleverageuiprocess/logo.jpg')->end()
            ->end();

        return $tb;
    }
}
