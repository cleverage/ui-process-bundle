<?php
namespace CleverAge\ProcessUiBundle\DependencyInjection\Compiler;

use CleverAge\ProcessUiBundle\Monolog\Handler\ProcessLogHandler;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class RegisterLogHandlerCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $loggers = [
            'monolog.logger.cleverage_process',
            'monolog.logger.cleverage_process_task',
            'monolog.logger.cleverage_process_transformer'
        ];
        foreach ($loggers as $logger) {
            if ($container->has($logger)) {
                $container
                    ->getDefinition($logger)
                    ->addMethodCall('pushHandler', [new Reference(ProcessLogHandler::class)]);
            }
        }
    }
}
