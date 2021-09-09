<?php
namespace CleverAge\ProcessUiBundle\DependencyInjection;

use Monolog\Logger;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('clever_age_process_ui');
        $treeBuilder->getRootNode()
            ->children()
                ->arrayNode('index_logs')
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->scalarNode('level')
                            ->defaultValue('ERROR')
                            ->validate()
                                ->ifNotInArray(array_flip(Logger::getLevels()))
                                ->thenInvalid('Invalid log level. Valid levels are '. implode(', ', array_flip(Logger::getLevels())))
                        ->end()
                    ->end()
            ->end();

        return $treeBuilder;
    }
}
