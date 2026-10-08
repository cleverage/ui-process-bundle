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

namespace CleverAge\UiProcessBundle\Notifier;

use CleverAge\UiProcessBundle\Event\ProcessExecutionEndedEvent;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Notifier\Notifier;
use Symfony\Component\Notifier\NotifierInterface;
use Symfony\Component\Notifier\Recipient\Recipient;
use Symfony\Component\Notifier\Recipient\RecipientInterface;

/**
 * Sends a notification at the end of a process execution, according to the "notification" configuration of the
 * bundle, overridden by the "notification" option of the process.
 *
 * @phpstan-import-type NotificationOptions from ProcessConfigurationsManager
 *
 * @phpstan-type ResolvedNotificationOptions array{
 *      'enabled': bool,
 *      'statuses': string[],
 *      'channels': list<string>,
 *      'recipients': array<array{'email': ?string, 'phone': ?string}>
 *  }
 */
final readonly class ProcessExecutionNotifier implements EventSubscriberInterface
{
    /**
     * @param ResolvedNotificationOptions $defaultOptions
     */
    public function __construct(
        private ProcessConfigurationsManager $processConfigurationsManager,
        private array $defaultOptions,
        private ?NotifierInterface $notifier = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function onProcessExecutionEnded(ProcessExecutionEndedEvent $event): void
    {
        $processExecution = $event->processExecution;
        $options = $this->getOptions($processExecution->code);
        if (!$options['enabled']) {
            return;
        }
        $trigger = NotificationTrigger::fromProcessExecution($processExecution);
        if (!$trigger instanceof NotificationTrigger || !\in_array($trigger->value, $options['statuses'], true)) {
            return;
        }
        if (!$this->notifier instanceof NotifierInterface) {
            $this->logger?->warning('The notification of the process execution is not sent: the notifier is not enabled (framework.notifier).', ['process' => $processExecution->code]);

            return;
        }

        try {
            $this->notifier->send(
                new ProcessExecutionNotification($processExecution, $trigger, $event->error, $options['channels']),
                ...$this->getRecipients($options['recipients'])
            );
        } catch (\Throwable $exception) {
            // A notification failure must not change the result of the process
            $this->logger?->error('Unable to send the notification of the process execution: {error}', [
                'process' => $processExecution->code,
                'error' => $exception->getMessage(),
                'exception' => $exception,
            ]);
        }
    }

    /**
     * @return ResolvedNotificationOptions
     */
    public function getOptions(string $processCode): array
    {
        $processOptions = $this->processConfigurationsManager->getNotificationOptions($processCode) ?? [];

        return [
            'enabled' => $processOptions['enabled'] ?? $this->defaultOptions['enabled'],
            'statuses' => $processOptions['statuses'] ?? $this->defaultOptions['statuses'],
            'channels' => $processOptions['channels'] ?? $this->defaultOptions['channels'],
            'recipients' => $processOptions['recipients'] ?? $this->defaultOptions['recipients'],
        ];
    }

    public static function getSubscribedEvents(): array
    {
        return [ProcessExecutionEndedEvent::class => 'onProcessExecutionEnded'];
    }

    /**
     * @param array<array{'email': ?string, 'phone': ?string}> $recipients
     *
     * @return RecipientInterface[] the configured recipients, the admin recipients of the notifier by default
     */
    private function getRecipients(array $recipients): array
    {
        if ([] === $recipients) {
            return $this->notifier instanceof Notifier ? $this->notifier->getAdminRecipients() : [];
        }

        return array_map(
            static fn (array $recipient): Recipient => new Recipient($recipient['email'] ?? '', $recipient['phone'] ?? ''),
            $recipients
        );
    }
}
