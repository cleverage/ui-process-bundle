<?php
namespace CleverAge\ProcessUiBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

class CleverAgeProcessUiExtension extends Extension implements PrependExtensionInterface
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');
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
                'assets' => ['json_manifest_path' => null]
            ]
        );
    }
}
