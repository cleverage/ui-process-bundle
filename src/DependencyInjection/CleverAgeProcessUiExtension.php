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

use CleverAge\ProcessUiBundle\Message\LogIndexerMessage;
use CleverAge\ProcessUiBundle\Message\ProcessRunMessage;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Finder\Finder;

class CleverAgeProcessUiExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $this->findServices($container, __DIR__.'/../../config/services');

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
                'migrations_paths' => ['CleverAgeUiProcess' => \dirname(__DIR__).'/Migrations'],
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
                            'retry_strategy' => ['max_retries' => 0],
                        ],
                        [
                            'name' => 'index_logs',
                            'dsn' => 'doctrine://default',
                            'retry_strategy' => ['max_retries' => 0],
                        ],
                    ],
                    'routing' => [
                        ProcessRunMessage::class => 'run_process',
                        LogIndexerMessage::class => 'index_logs',
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
