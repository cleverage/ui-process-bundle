<?php

/*
 * This file is part of the CleverAge/UiProcessBundle package.
 *
 * Copyright (c) Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\UiProcessBundle\DependencyInjection\Compiler;

use CleverAge\UiProcessBundle\Entity\ProcessExecutionInterface;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ResolveTargetEntityPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $container->findDefinition('doctrine.orm.listeners.resolve_target_entity')
            ->addMethodCall(
                'addResolveTargetEntity',
                [ProcessExecutionInterface::class, $container->getParameter('cleverage_ui_process.entity.process_execution.class'), []],
            )
            ->addTag('doctrine.event_listener', ['event' => Events::loadClassMetadata])
            ->addTag('doctrine.event_listener', ['event' => Events::onClassMetadataNotFound])
        ;
    }
}
