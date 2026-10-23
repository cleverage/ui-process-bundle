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

use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadedFileSubscriberPass;
use CleverAge\UiProcessBundle\EventSubscriber\UploadedFileSubscriber;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;

#[CoversClass(UploadedFileSubscriberPass::class)]
class UploadedFileSubscriberPassTest extends TestCase
{
    public function testTheFailureTransportsAreGivenToTheSubscriber(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(UploadedFileSubscriberPass::SUBSCRIBER, new Definition(UploadedFileSubscriber::class, [new Reference('manager')]));
        $failureSenders = new Reference('failure_senders_locator');
        $failureTransportsByName = ['execute_process' => 'failed', 'failed' => 'failed'];
        $container->setDefinition(UploadedFileSubscriberPass::FAILURE_LISTENER, new Definition(SendFailedMessageToFailureTransportListener::class, [$failureSenders, new Reference('logger'), $failureTransportsByName]));

        (new UploadedFileSubscriberPass())->process($container);

        $arguments = $container->getDefinition(UploadedFileSubscriberPass::SUBSCRIBER)->getArguments();
        self::assertSame($failureSenders, $arguments[1]);
        self::assertSame($failureTransportsByName, $arguments[2]);
    }

    /**
     * Before Symfony 6.4.34 / 7.4.6 / 8.0.6, the listener has no "failure transports by name" argument.
     */
    public function testWithoutFailureTransportsByName(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(UploadedFileSubscriberPass::SUBSCRIBER, new Definition(UploadedFileSubscriber::class, [new Reference('manager')]));
        $failureSenders = new Reference('failure_senders_locator');
        $container->setDefinition(UploadedFileSubscriberPass::FAILURE_LISTENER, new Definition(SendFailedMessageToFailureTransportListener::class, [$failureSenders, new Reference('logger')]));

        (new UploadedFileSubscriberPass())->process($container);

        $arguments = $container->getDefinition(UploadedFileSubscriberPass::SUBSCRIBER)->getArguments();
        self::assertSame($failureSenders, $arguments[1]);
        self::assertArrayNotHasKey(2, $arguments);
    }

    /**
     * Symfony removes the listener when no failure transport is configured.
     */
    public function testWithoutFailureTransport(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(UploadedFileSubscriberPass::SUBSCRIBER, new Definition(UploadedFileSubscriber::class, [new Reference('manager')]));

        (new UploadedFileSubscriberPass())->process($container);

        self::assertEquals([new Reference('manager')], $container->getDefinition(UploadedFileSubscriberPass::SUBSCRIBER)->getArguments());
    }

    #[DoesNotPerformAssertions]
    public function testWithoutSubscriber(): void
    {
        $container = new ContainerBuilder();
        $container->setDefinition(UploadedFileSubscriberPass::FAILURE_LISTENER, new Definition(SendFailedMessageToFailureTransportListener::class, [new Reference('failure_senders_locator')]));

        (new UploadedFileSubscriberPass())->process($container);
    }
}
