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

use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use Monolog\Level;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function __construct(private readonly string $env)
    {
    }

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('clever_age_ui_process');
        /** @var ArrayNodeDefinition $rootNode */
        $rootNode = $treeBuilder->getRootNode();

        $this->addSecuritySection($rootNode);
        $this->addLogSection($rootNode);
        $this->addDesignSection($rootNode);
        $this->addNotificationSection($rootNode);

        return $treeBuilder;
    }

    protected function addSecuritySection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('security')->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('roles')->defaultValue(['ROLE_ADMIN'])->scalarPrototype()->end() // Roles displayed inside user edit form
                    ->end()
                ->end()
            ->end()
        ;
    }

    protected function addLogSection(ArrayNodeDefinition $node): void
    {
        $defaultLevel = 'dev' === $this->env ? Level::Debug->name : Level::Info->name;
        $node
            ->children()
                ->arrayNode('logs')->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('store_in_database')->defaultValue(true)->end() // enable/disable store log in database (log_record table)
                        ->scalarNode('database_level')->defaultValue($defaultLevel)->end() // min log level to store log record in database
                        ->scalarNode('file_level')->defaultValue($defaultLevel)->end() // min log level to store log record in file
                        ->scalarNode('report_increment_level')->defaultValue(Level::Warning->name)->end() // min log level to increment process execution report
                    ->end()
                ->end()
            ->end()
        ;
    }

    protected function addDesignSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('design')->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('logo_path')->defaultValue('bundles/cleverageuiprocess/logo.jpg')->end()
                    ->end()
                ->end()
            ->end()
        ;
    }

    protected function addNotificationSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('notification')->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end() // notify the end of the process executions (requires symfony/notifier), can be overridden by process
                        ->arrayNode('statuses') // ends of process executions to notify
                            ->defaultValue([NotificationTrigger::Failed->value, NotificationTrigger::FinishWithReport->value])
                            ->enumPrototype()->values(NotificationTrigger::values())->end()
                        ->end()
                        ->arrayNode('channels')->scalarPrototype()->end()->end() // notifier channels (e.g. "chat/slack", "email"), the channel policy of the notifier if empty
                        ->arrayNode('recipients') // the admin recipients of the notifier if empty
                            ->arrayPrototype()
                                ->children()
                                    ->scalarNode('email')->defaultNull()->end()
                                    ->scalarNode('phone')->defaultNull()->end()
                                ->end()
                                ->validate()
                                    ->ifTrue(static fn (array $recipient): bool => null === $recipient['email'] && null === $recipient['phone'])
                                    ->thenInvalid('A notification recipient must have an "email" or a "phone".')
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
