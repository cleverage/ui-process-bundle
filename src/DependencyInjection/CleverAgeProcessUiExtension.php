<?php

namespace CleverAge\ProcessUiBundle\DependencyInjection;

use CleverAge\ProcessUiBundle\Message\LogIndexerMessage;
use CleverAge\ProcessUiBundle\Message\ProcessRunMessage;
use Exception;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class CleverAgeProcessUiExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
        $loader->load('services.yaml');

        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);
        $container->setParameter('clever_age_process_ui.index_logs.enabled', $config['index_logs']['enabled']);
    }

    /**
     * Update default configuration for migrations, twig form theme, assets ...
     */
    public function prepend(ContainerBuilder $container): void
    {
        $container->loadFromExtension(
            'doctrine_migrations',
            [
                'migrations_paths' => ['CleverAgeProcessUi' => dirname(__DIR__) . '/Migrations']
            ]
        );
        $container->loadFromExtension(
            'framework',
            [
                'assets' => ['json_manifest_path' => null],
                'messenger' => [
                    'transport' => [
                        [
                            'name' => 'run_process',
                            'dsn' => 'doctrine://default',
                            'retry_strategy' => ['max_retries' => 0]
                        ],
                        [
                            'name' => 'index_logs',
                            'dsn' => 'doctrine://default',
                            'retry_strategy' => ['max_retries' => 0]
                        ]
                    ],
                    'routing' => [
                        ProcessRunMessage::class => 'run_process',
                        LogIndexerMessage::class => 'index_logs'
                    ]
                ]
            ]
        );
    }
}
