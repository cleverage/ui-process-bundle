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

namespace CleverAge\UiProcessBundle\EventSubscriber;

use CleverAge\UiProcessBundle\Manager\UploadedFileManager;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use Psr\Container\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;

/**
 * Deletes the uploaded input of a ProcessExecuteMessage that failed in a Messenger worker once it will not be executed
 * again: neither retried (retry strategy of the transport) nor sent to a failure transport (messenger:failed:retry).
 *
 * The ProcessExecuteHandler deletes it itself after a successful execution, and after a failed one outside of a worker
 * (synchronous handling).
 *
 * @internal
 */
final class UploadedFileSubscriber implements EventSubscriberInterface
{
    /**
     * Message being handled by a worker.
     */
    private ?object $workerMessage = null;

    /**
     * Arguments of the Symfony SendFailedMessageToFailureTransportListener.
     *
     * @param ContainerInterface|null    $failureSenders          failure transports by transport name
     * @param array<string, string>|null $failureTransportsByName names of the failure transports by transport name,
     *                                                            null before Symfony 6.4.34 / 7.4.6 / 8.0.6 (no such
     *                                                            argument: a message already sent to a failure
     *                                                            transport is not sent again)
     */
    public function __construct(
        private readonly UploadedFileManager $uploadedFileManager,
        private readonly ?ContainerInterface $failureSenders = null,
        private readonly ?array $failureTransportsByName = null,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerMessageReceivedEvent::class => 'onMessageReceived',
            WorkerMessageHandledEvent::class => 'onMessageHandled',
            WorkerMessageFailedEvent::class => 'onMessageFailed',
        ];
    }

    /**
     * Whether the message is handled by a worker: if it fails, its input is deleted by this subscriber.
     */
    public function isHandledByWorker(ProcessExecuteMessage $message): bool
    {
        return $message === $this->workerMessage;
    }

    public function onMessageReceived(WorkerMessageReceivedEvent $event): void
    {
        $this->workerMessage = $event->getEnvelope()->getMessage();
    }

    public function onMessageHandled(WorkerMessageHandledEvent $event): void
    {
        $this->workerMessage = null;
    }

    public function onMessageFailed(WorkerMessageFailedEvent $event): void
    {
        $this->workerMessage = null;
        $message = $event->getEnvelope()->getMessage();
        // deleteInput is uninitialized in a message queued by a previous version of the bundle
        // @phpstan-ignore nullCoalesce.property
        if (!$message instanceof ProcessExecuteMessage || !($message->deleteInput ?? false) || $event->willRetry()
            || $this->isSentToFailureTransport($event)
        ) {
            return;
        }

        $this->uploadedFileManager->deleteInput($message->input);
    }

    /**
     * Whether the Symfony SendFailedMessageToFailureTransportListener sends the message to a failure transport, from
     * which it can be retried (messenger:failed:retry).
     */
    private function isSentToFailureTransport(WorkerMessageFailedEvent $event): bool
    {
        $receiverName = $event->getReceiverName();
        if (!$this->failureSenders?->has($receiverName)) {
            return false;
        }
        $stamp = $event->getEnvelope()->last(SentToFailureTransportStamp::class);
        if (!$stamp instanceof SentToFailureTransportStamp) {
            return true;
        }

        // Already sent to a failure transport (e.g. received from it by messenger:failed:retry): sent again only to
        // another failure transport, otherwise the message is dropped
        return null !== $this->failureTransportsByName
            && ($this->failureTransportsByName[$receiverName] ?? null) !== $receiverName
            && $stamp->getOriginalReceiverName() !== $receiverName;
    }
}
