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

namespace CleverAge\ProcessUiBundle\DependencyInjection;

use CleverAge\ProcessUiBundle\Controller\Admin\ProcessDashboardController;
use CleverAge\ProcessUiBundle\Controller\Admin\UserCrudController;
use CleverAge\ProcessUiBundle\Entity\User;
use CleverAge\ProcessUiBundle\Message\ProcessExecuteMessage;
use CleverAge\ProcessUiBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\ProcessUiBundle\Monolog\Handler\ProcessHandler;
use Monolog\Level;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Finder\Finder;

final class CleverAgeProcessUiExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->findServices($container, __DIR__.'/../../config/services');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $container->getDefinition(UserCrudController::class)
            ->setArgument('$roles', array_combine($config['security']['roles'], $config['security']['roles']));
        $container->getDefinition(ProcessHandler::class)
            ->addMethodCall('setReportIncrementLevel', [$config['logs']['report_increment_level']]);
        $container->getDefinition(ProcessDashboardController::class)
            ->setArgument('$logoPath', $config['design']['logo_path']);
    }

    /**
     * Update default configuration for migrations, twig form theme, assets ...
     */
    public function prepend(ContainerBuilder $container): void
    {
        $env = $container->getParameter("kernel.environment");
        $container->loadFromExtension(
            'monolog',
            [
                'handlers' => [
                    'pb_ui_file' => [
                        'type' => 'service',
                        'id' => ProcessHandler::class
                    ],
                    'pb_ui_orm' => [
                        'type' => 'service',
                        'id' => DoctrineProcessHandler::class
                    ]
                ]
            ]
        );
        if ("dev" === $env) {
            $container->loadFromExtension(
                'monolog',
                [
                    'handlers' => [
                        'pb_ui_file_filter' => [
                            'type' => 'filter',
                            'min_level' => Level::Debug->name,
                            'handler' => 'pb_ui_file',
                            'channels' => ['cleverage_process', 'cleverage_process_task']
                        ],
                        'pb_ui_orm_filter' => [
                            'type' => 'filter',
                            'min_level' => Level::Debug->name,
                            'handler' => 'pb_ui_orm',
                            'channels' => ["cleverage_process", "cleverage_process_task"]
                        ],
                    ]
                ]
            );
        } else {
            $container->loadFromExtension(
                'monolog',
                [
                    'handlers' => [
                        'pb_ui_file_filter' => [
                            'type' => 'filter',
                            'min_level' => Level::Info->name,
                            'handler' => 'pb_ui_file',
                            'channels' => ['cleverage_process', 'cleverage_process_task']
                        ],
                        'pb_ui_orm_filter' => [
                            'type' => 'filter',
                            'min_level' => Level::Info->name,
                            'handler' => 'pb_ui_orm',
                            'channels' => ["cleverage_process", "cleverage_process_task"]
                        ],
                    ]
                ]
            );
        }
        $container->loadFromExtension(
            'doctrine_migrations',
            [
                'migrations_paths' => ['CleverAge\ProcessUiBundle\Migrations' => \dirname(__DIR__).'/Migrations'],
            ]
        );
        $container->loadFromExtension(
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
