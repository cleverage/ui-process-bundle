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

namespace CleverAge\UiProcessBundle\Tests\DependencyInjection\Compiler;

use CleverAge\UiProcessBundle\DependencyInjection\Compiler\MonologFilterLevelPass;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use Monolog\Handler\FilterHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

#[CoversClass(MonologFilterLevelPass::class)]
class MonologFilterLevelPassTest extends TestCase
{
    /**
     * Each filter accepts the level of the handler it wraps (an environment variable placeholder is given as it is).
     */
    public function testTheFiltersAcceptTheLevelOfTheirHandler(): void
    {
        $container = $this->createContainer('Info', '%env(UI_DATABASE_LOG_LEVEL)%');

        (new MonologFilterLevelPass())->process($container);

        self::assertEquals(
            [new Reference('cleverage_ui_process.monolog_handler.process'), 'Info', 'EMERGENCY', true],
            $container->getDefinition('monolog.handler.pb_ui_file_filter')->getArguments()
        );
        self::assertSame('%env(UI_DATABASE_LOG_LEVEL)%', $container->getDefinition('monolog.handler.pb_ui_orm_filter')->getArgument(1));
    }

    /**
     * Levels set otherwise (accepted levels, another minimum level) are kept.
     */
    public function testOtherFilterLevelsAreKept(): void
    {
        $container = $this->createContainer('Info', 'Error');
        $container->getDefinition('monolog.handler.pb_ui_file_filter')->replaceArgument(1, ['ERROR', 'CRITICAL']);
        $container->getDefinition('monolog.handler.pb_ui_orm_filter')->replaceArgument(1, 'WARNING');

        (new MonologFilterLevelPass())->process($container);

        self::assertSame(['ERROR', 'CRITICAL'], $container->getDefinition('monolog.handler.pb_ui_file_filter')->getArgument(1));
        self::assertSame('WARNING', $container->getDefinition('monolog.handler.pb_ui_orm_filter')->getArgument(1));
    }

    /**
     * Without the MonologBundle, or without the level of a handler.
     */
    #[DoesNotPerformAssertions]
    public function testWithoutFilters(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cleverage_ui_process.monolog_handler.process', new Definition(ProcessHandler::class));
        $container->setDefinition('monolog.handler.pb_ui_orm_filter', new Definition(FilterHandler::class, [new Reference('cleverage_ui_process.monolog_handler.doctrine_process'), 'DEBUG']));

        (new MonologFilterLevelPass())->process($container);
    }

    public function testWithoutHandlerLevel(): void
    {
        $container = $this->createContainer('Info', 'Error');
        $container->setDefinition('cleverage_ui_process.monolog_handler.process', new Definition(ProcessHandler::class));

        (new MonologFilterLevelPass())->process($container);

        self::assertSame('DEBUG', $container->getDefinition('monolog.handler.pb_ui_file_filter')->getArgument(1));
        self::assertSame('Error', $container->getDefinition('monolog.handler.pb_ui_orm_filter')->getArgument(1));
    }

    /**
     * The definitions of the MonologBundle for the "filter" handlers of the bundle, without "min_level".
     */
    private function createContainer(string $fileLevel, string $databaseLevel): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setDefinition('cleverage_ui_process.monolog_handler.process', (new Definition(ProcessHandler::class))->setArgument('$level', $fileLevel));
        $container->setDefinition('cleverage_ui_process.monolog_handler.doctrine_process', (new Definition(DoctrineProcessHandler::class))->setArgument('$level', $databaseLevel));
        foreach (MonologFilterLevelPass::FILTERS as $filterId => $handlerId) {
            $container->setDefinition($filterId, new Definition(FilterHandler::class, [new Reference($handlerId), 'DEBUG', 'EMERGENCY', true]));
        }

        return $container;
    }
}
