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
 * Gives the failure transports of the application to the UploadedFileSubscriber: the uploaded input of a message sent
 * to a failure transport is kept, the message can be retried with messenger:failed:retry. Symfony only defines the
 * SendFailedMessageToFailureTransportListener when a failure transport is configured.
 *
 * @internal
 */
final readonly class UploadedFileSubscriberPass implements CompilerPassInterface
{
    public const SUBSCRIBER = 'cleverage_ui_process.event_subscriber.uploaded_file';
    public const FAILURE_LISTENER = 'messenger.failure.send_failed_message_to_failure_transport_listener';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(self::SUBSCRIBER) || !$container->hasDefinition(self::FAILURE_LISTENER)) {
            return;
        }

        $failureListenerArguments = $container->getDefinition(self::FAILURE_LISTENER)->getArguments();
        $subscriber = $container->getDefinition(self::SUBSCRIBER)->setArgument(1, $failureListenerArguments[0] ?? null);
        // Failure transport names by transport name: since Symfony 6.4.34 / 7.4.6 / 8.0.6
        if (\array_key_exists(2, $failureListenerArguments)) {
            $subscriber->setArgument(2, $failureListenerArguments[2]);
        }
    }
}
