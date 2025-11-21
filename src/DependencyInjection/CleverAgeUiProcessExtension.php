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
use CleverAge\UiProcessBundle\Controller\Admin\UserCrudController;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Finder\Finder;

final class CleverAgeUiProcessExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->findServices($container, __DIR__.'/../../config/services');

        /** @var string $env */
        $env = $container->getParameter('kernel.environment');
        $configuration = new Configuration($env);
        $config = $this->processConfiguration($configuration, $configs);

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

        $container->getDefinition(ProcessDashboardController::class)
            ->setArgument('$logoPath', $config['design']['logo_path']);
    }

    /**
     * Update default configuration for migrations, twig form theme, assets ...
     */
    public function prepend(ContainerBuilder $container): void
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
        $container->loadFromExtension(
            'doctrine_migrations',
            [
                'migrations_paths' => ['CleverAge\UiProcessBundle\Migrations' => \dirname(__DIR__).'/Migrations'],
            ]
        );
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
                            'login_path' => 'process_login',
                            'check_path' => 'process_login',
                        ],
                        'logout' => [
                            'path' => 'process_logout',
                            'target' => 'process_login',
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
