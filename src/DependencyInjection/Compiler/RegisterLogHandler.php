<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\DependencyInjection\Compiler;

use CleverAge\ProcessUiBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\ProcessUiBundle\Monolog\Handler\ProcessHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RegisterLogHandler implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('monolog.logger')) {
            return;
        }
        $loggers = [
            'monolog.logger.cleverage_process',
            'monolog.logger.cleverage_process_task',
            'monolog.logger.cleverage_process_transformer',
        ];
        foreach ($loggers as $logger) {
            if ($container->has($logger)) {
                $container
                    ->getDefinition($logger)
                    ->addMethodCall('pushHandler', [new Reference(ProcessHandler::class)])
                    ->addMethodCall('pushHandler', [new Reference(DoctrineProcessHandler::class)]);
            }
        }
    }
}
