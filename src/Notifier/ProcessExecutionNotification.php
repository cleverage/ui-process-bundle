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

use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use Symfony\Component\Notifier\Notification\Notification;

/**
 * Notification sent at the end of a process execution.
 */
class ProcessExecutionNotification extends Notification
{
    /**
     * @param list<string> $channels empty to use the channel policy of the notifier (by importance)
     */
    public function __construct(
        public readonly ProcessExecution $processExecution,
        public readonly NotificationTrigger $trigger,
        ?\Throwable $error = null,
        array $channels = [],
    ) {
        parent::__construct('', $channels);
        if ($error instanceof \Throwable) {
            // Before the subject: the exception replaces it
            $this->exception($error);
        }
        $this->subject($this->buildSubject());
        $this->content($this->buildContent($error));
        $this->importance(match ($trigger) {
            NotificationTrigger::Failed => self::IMPORTANCE_HIGH,
            NotificationTrigger::FinishWithReport => self::IMPORTANCE_MEDIUM,
            NotificationTrigger::Finish => self::IMPORTANCE_LOW,
        });
    }

    protected function buildSubject(): string
    {
        return \sprintf('Process "%s" %s', $this->processExecution->code, match ($this->trigger) {
            NotificationTrigger::Failed => 'failed',
            NotificationTrigger::FinishWithReport => 'finished with reported logs',
            NotificationTrigger::Finish => 'finished',
        });
    }

    protected function buildContent(?\Throwable $error): string
    {
        $lines = [
            'Status: '.$this->processExecution->status->value,
            'Start date: '.$this->processExecution->startDate->format(\DateTimeInterface::ATOM),
            'Duration: '.($this->processExecution->duration() ?? '-'),
        ];
        $reportedLevels = NotificationTrigger::getReportedLevels($this->processExecution);
        if ([] !== $reportedLevels) {
            $lines[] = 'Report: '.implode(', ', array_map(
                static fn (string $level, mixed $count): string => \sprintf('%s: %s', $level, \is_scalar($count) ? $count : get_debug_type($count)),
                array_keys($reportedLevels),
                $reportedLevels
            ));
        }
        if ($error instanceof \Throwable) {
            $lines[] = 'Error: '.$error->getMessage();
        }
        $lines[] = 'Log file: '.$this->processExecution->code.'/'.$this->processExecution->logFilename;
        if (null !== $this->processExecution->getId()) {
            $lines[] = 'Process execution: '.$this->processExecution->getId();
        }

        return implode("\n", $lines);
    }
}
