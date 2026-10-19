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

/**
 * @internal
 */
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
        $this->addProcessExecutionSection($rootNode);

        return $treeBuilder;
    }

    protected function addSecuritySection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('security')->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('roles')
                            ->info('Roles that can be assigned to a user in the user edit form (ROLE_USER is always granted).')
                            ->defaultValue(['ROLE_ADMIN'])->scalarPrototype()->end()
                        ->end()
                        ->booleanNode('configure_firewall')
                            ->info('Add the "process_user_provider" user provider and configure the "main" firewall of the application (overriding its provider, login form and logout); false: configure the security of the UI in the application. Must be a literal boolean.')
                            ->defaultTrue()
                        ->end()
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
                        ->booleanNode('store_in_database')
                            ->info('Store the process logs in database (log_record table). The log file is always written.')
                            ->defaultValue(true)
                        ->end()
                        ->scalarNode('database_level')
                            ->info('Minimum Monolog level (name or value) of the log records stored in database (Debug in "dev" environment, else Info).')
                            ->defaultValue($defaultLevel)
                        ->end()
                        ->scalarNode('file_level')
                            ->info('Minimum Monolog level (name or value) of the log records written in the execution log file (Debug in "dev" environment, else Info).')
                            ->defaultValue($defaultLevel)
                        ->end()
                        ->scalarNode('report_increment_level')
                            ->info('Minimum Monolog level (name) from which a log record increments the execution report.')
                            ->defaultValue(Level::Warning->name)
                        ->end()
                        ->integerNode('database_max_days')
                            ->info('Days after which the log records are deleted from database by the cleverage:ui-process:purge-logs command, null to keep them. Can be overridden by process.')
                            ->defaultNull()->min(1)
                        ->end()
                        ->integerNode('file_max_days')
                            ->info('Days after which the log files are deleted by the cleverage:ui-process:purge-logs command, null to keep them. Can be overridden by process.')
                            ->defaultNull()->min(1)
                        ->end()
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
                        ->scalarNode('logo_path')
                            ->info('Path of the logo displayed in the dashboard, relative to the public directory.')
                            ->defaultValue('bundles/cleverageuiprocess/logo.jpg')
                        ->end()
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
                        ->booleanNode('enabled')
                            ->info('Notify the end of the process executions (requires symfony/notifier). Can be overridden by process.')
                            ->defaultFalse()
                        ->end()
                        ->arrayNode('statuses')
                            ->info('Ends of process executions to notify.')
                            ->defaultValue([NotificationTrigger::Failed->value, NotificationTrigger::FinishWithReport->value])
                            ->enumPrototype()->values(NotificationTrigger::values())->end()
                        ->end()
                        ->arrayNode('channels')
                            ->info('Notifier channels (e.g. "chat/slack", "email"); the channel policy of the notifier if empty.')
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('recipients')
                            ->info('Recipients of the notifications; the admin recipients of the notifier if empty.')
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

    protected function addProcessExecutionSection(ArrayNodeDefinition $node): void
    {
        $node
            ->children()
                ->arrayNode('process_execution')->addDefaultsIfNotSet()
                    ->children()
                        ->integerNode('heartbeat_interval')
                            ->info('Seconds between two updates of the heartbeat of a running process execution (and checks of its stop request).')
                            ->defaultValue(5)->min(1)
                        ->end()
                        ->integerNode('stale_after')
                            ->info('Seconds without heartbeat after which a started process execution is considered as not responding.')
                            ->defaultValue(900)->min(1)
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
    }
}
