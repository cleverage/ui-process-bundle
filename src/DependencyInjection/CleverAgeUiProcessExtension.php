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

use CleverAge\UiProcessBundle\Controller\Admin\ProcessDashboardController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessExecutionCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\UserCrudController;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use Monolog\Level;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Notifier\NotifierInterface;

final class CleverAgeUiProcessExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->findServices($container, __DIR__.'/../../config/services');

        $configuration = $this->getConfiguration($configs, $container);
        /** @var array{
         *     security: array{roles: list<string>, configure_firewall: bool},
         *     logs: array{store_in_database: bool, database_level: string, file_level: string, report_increment_level: string, database_max_days: ?int, file_max_days: ?int},
         *     design: array{logo_path: string},
         *     notification: array{enabled: bool, ...},
         *     process_execution: array{heartbeat_interval: int, stale_after: int},
         * } $config
         */
        $config = $this->processConfiguration($configuration, $configs);
        $this->validateLogLevels($config['logs'], $container);

        $container->getDefinition(UserCrudController::class)
            ->setArgument('$roles', array_combine($config['security']['roles'], $config['security']['roles']));

        $container->getDefinition('cleverage_ui_process.monolog_handler.process')
            ->setArgument('$level', $config['logs']['file_level'])
            ->addMethodCall('setReportIncrementLevel', [$config['logs']['report_increment_level']])
        ;
        $container->getDefinition('cleverage_ui_process.monolog_handler.doctrine_process')
            ->setArgument('$level', $config['logs']['database_level']);
        if (!$config['logs']['store_in_database']) {
            $container->getDefinition('cleverage_ui_process.monolog_handler.doctrine_process')
                ->addMethodCall('disable');
        }
        $container->getDefinition('cleverage_ui_process.command.purge_logs')
            ->setArgument('$databaseMaxDays', $config['logs']['database_max_days'])
            ->setArgument('$fileMaxDays', $config['logs']['file_max_days']);

        $container->getDefinition(ProcessDashboardController::class)
            ->setArgument('$logoPath', $config['design']['logo_path']);

        $container->getDefinition('cleverage_ui_process.stop_checker.process_execution')
            ->replaceArgument(2, $config['process_execution']['heartbeat_interval']);
        $container->getDefinition(ProcessExecutionCrudController::class)
            ->setArgument('$staleAfter', $config['process_execution']['stale_after']);

        if (interface_exists(NotifierInterface::class)) {
            $container->getDefinition('cleverage_ui_process.notifier.process_execution')
                ->setArgument('$defaultOptions', $config['notification']);
        } else {
            if ($config['notification']['enabled']) {
                throw new LogicException('The notification of the process executions requires symfony/notifier: run "composer require symfony/notifier".');
            }
            $container->removeDefinition('cleverage_ui_process.notifier.process_execution');
        }
    }

    /**
     * The log levels are validated here instead of in the configuration tree, so that environment variables are
     * still allowed: an environment variable is only resolved at runtime (its value is then validated by Monolog).
     *
     * @param array{database_level: mixed, file_level: mixed, report_increment_level: mixed, ...} $logs
     */
    private function validateLogLevels(array $logs, ContainerBuilder $container): void
    {
        $levels = [
            // Logger::toMonologLevel() of the handlers: a level name (case-insensitive) or value
            'database_level' => true,
            'file_level' => true,
            // Level::fromName() of ProcessHandler::setReportIncrementLevel(): a level name (case-insensitive)
            'report_increment_level' => false,
        ];
        foreach ($levels as $option => $valueAllowed) {
            $level = $logs[$option];
            $usedEnvs = [];
            $container->resolveEnvPlaceholders($level, null, $usedEnvs);
            if ([] !== $usedEnvs) {
                continue;
            }
            if ($valueAllowed && (\is_int($level) || (\is_string($level) && is_numeric($level)))) {
                $valid = Level::tryFrom((int) $level) instanceof Level;
            } else {
                $valid = \is_string($level) && \in_array(strtoupper($level), Level::NAMES, true);
            }
            if (!$valid) {
                throw new InvalidConfigurationException(\sprintf('Invalid configuration for path "%s.logs.%s": invalid Monolog level %s, use one of: %s.', $this->getAlias(), $option, json_encode($level), implode(', ', Level::NAMES)));
            }
        }
    }

    /**
     * The default log levels depend on the kernel environment, so the configuration cannot be instantiated without it
     * by the parent method (used by config:dump-reference, debug:config and the PHP config builders).
     *
     * @param array<mixed> $config
     */
    #[\Override]
    public function getConfiguration(array $config, ContainerBuilder $container): Configuration
    {
        /** @var string $env */
        $env = $container->getParameter('kernel.environment');

        return new Configuration($env);
    }

    /**
     * Configure the other bundles: Monolog handlers, migrations path, Messenger transport and security.
     *
     * The Messenger configuration is prepended (the application configuration wins). The other ones are appended, so
     * they override the application configuration: in particular the security one replaces the provider, login form
     * and logout of the application "main" firewall, unless the "security.configure_firewall" option is false.
     */
    public function prepend(ContainerBuilder $container): void
    {
        if ($container->hasExtension('monolog')) {
            $this->configureMonolog($container);
        }
        if ($container->hasExtension('doctrine_migrations')) {
            $container->loadFromExtension(
                'doctrine_migrations',
                [
                    'migrations_paths' => ['CleverAge\UiProcessBundle\Migrations' => __DIR__.'/../Migrations'],
                ]
            );
        }
        $container->prependExtensionConfig(
            'framework',
            [
                'messenger' => [
                    'transport' => [
                        [
                            'name' => 'execute_process',
                            'dsn' => 'doctrine://default',
                            'retry_strategy' => ['max_retries' => 0],
                        ],
                    ],
                    'routing' => [
                        ProcessExecuteMessage::class => 'execute_process',
                    ],
                ],
            ]
        );
        if ($container->hasExtension('security') && $this->isFirewallConfigured($container)) {
            $this->configureSecurity($container);
        }
    }

    /**
     * The "security.configure_firewall" option, read before the bundle configuration is processed: the last value
     * defined wins, as when it is processed.
     */
    private function isFirewallConfigured(ContainerBuilder $container): bool
    {
        $configureFirewall = true;
        foreach ($container->getExtensionConfig($this->getAlias()) as $config) {
            if (!\is_array($config['security'] ?? null) || !\array_key_exists('configure_firewall', $config['security'])) {
                continue;
            }
            $configureFirewall = $config['security']['configure_firewall'];
            if (!\is_bool($configureFirewall)) {
                throw new InvalidConfigurationException(\sprintf('The "%s.security.configure_firewall" option must be a boolean, "%s" given: parameters and environment variables are not supported, as it is read before they are resolved.', $this->getAlias(), get_debug_type($configureFirewall)));
            }
        }

        return $configureFirewall;
    }

    private function configureMonolog(ContainerBuilder $container): void
    {
        $container->loadFromExtension(
            'monolog',
            [
                'handlers' => [
                    'pb_ui_file' => [
                        'type' => 'service',
                        'id' => 'cleverage_ui_process.monolog_handler.process',
                    ],
                    'pb_ui_orm' => [
                        'type' => 'service',
                        'id' => 'cleverage_ui_process.monolog_handler.doctrine_process',
                    ],
                    'pb_ui_file_filter' => [
                        'type' => 'filter',
                        'handler' => 'pb_ui_file',
                        'channels' => ['cleverage_process', 'cleverage_process_task'],
                    ],
                    'pb_ui_orm_filter' => [
                        'type' => 'filter',
                        'handler' => 'pb_ui_orm',
                        'channels' => ['cleverage_process', 'cleverage_process_task'],
                    ],
                ],
            ]
        );
    }

    private function configureSecurity(ContainerBuilder $container): void
    {
        $container->loadFromExtension(
            'security',
            [
                'providers' => [
                    'process_user_provider' => [
                        'entity' => [
                            'class' => User::class,
                            'property' => 'email',
                        ],
                    ],
                ],
                'firewalls' => [
                    'main' => [
                        'provider' => 'process_user_provider',
                        'custom_authenticator' => ['cleverage_ui_process.security.http_process_execution_authenticator'],
                        'form_login' => [
                            'login_path' => 'cleverage_ui_process_login',
                            'check_path' => 'cleverage_ui_process_login',
                        ],
                        'logout' => [
                            'path' => 'cleverage_ui_process_logout',
                            'target' => 'cleverage_ui_process_login',
                            'clear_site_data' => '*',
                        ],
                    ],
                ],
            ]
        );
    }

    /**
     * Recursively import config files into container.
     */
    protected function findServices(ContainerBuilder $container, string $path, string $extension = 'yaml'): void
    {
        $finder = new Finder();
        $finder->in($path)
            ->name('*.'.$extension)->files();
        $loader = new YamlFileLoader($container, new FileLocator($path));
        foreach ($finder as $file) {
            $loader->load($file->getFilename());
        }
    }
}
