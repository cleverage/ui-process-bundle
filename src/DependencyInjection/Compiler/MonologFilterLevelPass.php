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

namespace CleverAge\UiProcessBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

/**
 * The "pb_ui_file_filter" and "pb_ui_orm_filter" Monolog handlers of the bundle (filtering the channels, see
 * CleverAgeUiProcessExtension::configureMonolog()) accept the level of the handler they wrap ("file_level",
 * "database_level"), instead of every level: the logger then runs its processors only for the records one of them
 * writes (e.g. not for the debug records of every task execution with the default "Info" level).
 *
 * Done here, as the levels of the bundle configuration are not known when the Monolog configuration is prepended (they
 * may be environment variables: their placeholders are given as they are, resolved at runtime).
 *
 * @internal
 */
final readonly class MonologFilterLevelPass implements CompilerPassInterface
{
    /** Filter handler => wrapped handler of the bundle */
    public const FILTERS = [
        'monolog.handler.pb_ui_file_filter' => 'cleverage_ui_process.monolog_handler.process',
        'monolog.handler.pb_ui_orm_filter' => 'cleverage_ui_process.monolog_handler.doctrine_process',
    ];

    public function process(ContainerBuilder $container): void
    {
        foreach (self::FILTERS as $filterId => $handlerId) {
            if (!$container->hasDefinition($filterId) || !$container->hasDefinition($handlerId)) {
                continue;
            }
            $filter = $container->getDefinition($filterId);
            $arguments = $filter->getArguments();
            $handlerArguments = $container->getDefinition($handlerId)->getArguments();
            // The default minimum level of a Monolog "filter" handler, without "accepted_levels"
            if (!\array_key_exists(1, $arguments) || 'DEBUG' !== $arguments[1] || !\array_key_exists('$level', $handlerArguments)) {
                continue;
            }
            $filter->replaceArgument(1, $handlerArguments['$level']);
        }
    }
}
