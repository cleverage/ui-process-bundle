<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\DependencyInjection;

use CleverAge\ProcessUiBundle\Controller\Admin\ProcessDashboardController;
use CleverAge\ProcessUiBundle\Controller\Admin\UserCrudController;
use CleverAge\ProcessUiBundle\Entity\User;
use CleverAge\ProcessUiBundle\Message\ProcessExecuteMessage;
use CleverAge\ProcessUiBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\ProcessUiBundle\Monolog\Handler\ProcessHandler;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

final class CleverAgeProcessUiExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $container->getDefinition(UserCrudController::class)
            ->setArgument('$roles', array_combine($config['security']['roles'], $config['security']['roles']));
        $container->getDefinition(DoctrineProcessHandler::class)
            ->addMethodCall('setEnabled', [$config['logs']['store_in_database']])
            ->addMethodCall('setLevel', [$config['logs']['database_level']]);
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
}
