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

use CleverAge\ProcessBundle\Event\ProcessEvent;
use CleverAge\ProcessBundle\Exception\ProcessStoppedException;
use CleverAge\UiProcessBundle\Entity\Enum\ProcessExecutionStatus;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Event\ProcessExecutionEndedEvent;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final readonly class ProcessEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ProcessHandler $processHandler,
        private DoctrineProcessHandler $doctrineProcessHandler,
        private ProcessExecutionManager $processExecutionManager,
        private ?EventDispatcherInterface $eventDispatcher = null,
    ) {
    }

    public function onProcessStart(ProcessEvent $event): void
    {
        if (false === $this->processHandler->hasFilename()) {
            $this->processHandler->setFilename(\sprintf('%s/%s.log', $event->getProcessCode(), Uuid::v4()));
        }
        if (!$this->processExecutionManager->getCurrentProcessExecution() instanceof ProcessExecution) {
            $processExecution = new ProcessExecution(
                $event->getProcessCode(),
                basename((string) $this->processHandler->getFilename()),
                $event->getProcessContext()
            );
            $this->processExecutionManager->setCurrentProcessExecution($processExecution)->save();
        }
    }

    public function success(ProcessEvent $event): void
    {
        if ($event->getProcessCode() === $this->processExecutionManager->getCurrentProcessExecution()?->getCode()) {
            $this->processExecutionManager->getCurrentProcessExecution()->setStatus(
                $this->isStoppedOnPurpose($event) ? ProcessExecutionStatus::Stopped : ProcessExecutionStatus::Finish
            );
            $this->processExecutionManager->getCurrentProcessExecution()->end();
            $this->endProcessExecution($event);
        }
    }

    public function fail(ProcessEvent $event): void
    {
        if ($event->getProcessCode() === $this->processExecutionManager->getCurrentProcessExecution()?->getCode()) {
            $this->processExecutionManager->getCurrentProcessExecution()->setStatus(
                $this->isStopped($event->getProcessError()) ? ProcessExecutionStatus::Stopped : ProcessExecutionStatus::Failed
            );
            $this->processExecutionManager->getCurrentProcessExecution()->end();
            $this->endProcessExecution($event);
        }
    }

    /**
     * Save the ended process execution, then forget it even if the save fails (it would stay the current one, and the
     * next process would be attached to it).
     */
    private function endProcessExecution(ProcessEvent $event): void
    {
        try {
            $this->processExecutionManager->save();
        } finally {
            $this->processExecutionManager->unsetProcessExecution($event->getProcessCode());
            $this->processHandler->close();
        }
        $this->dispatchEnded($event);
    }

    /**
     * The process has been stopped on purpose by a StopTask: it has ended normally, flagged as stopped
     * (ProcessEvent::isProcessStopped(), since the process bundle version following 5.4).
     */
    private function isStoppedOnPurpose(ProcessEvent $event): bool
    {
        return method_exists($event, 'isProcessStopped') && $event->isProcessStopped();
    }

    /**
     * The process has been stopped on a stop request: a ProcessStoppedException in the chain of the previous
     * exceptions (wrapped in a ProcessFailedException, by each parent process for a sub-process).
     */
    private function isStopped(?\Throwable $error): bool
    {
        for (; $error instanceof \Throwable; $error = $error->getPrevious()) {
            if ($error instanceof ProcessStoppedException) {
                return true;
            }
        }

        return false;
    }

    private function dispatchEnded(ProcessEvent $event): void
    {
        $processExecution = $this->processExecutionManager->getLastProcessExecution();
        if ($processExecution instanceof ProcessExecution) {
            $this->eventDispatcher?->dispatch(new ProcessExecutionEndedEvent($processExecution, $event->getProcessError()));
        }
    }

    public function flushDoctrineLogs(ProcessEvent $event): void
    {
        $this->doctrineProcessHandler->flush();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProcessEvent::EVENT_PROCESS_STARTED => 'onProcessStart',
            ProcessEvent::EVENT_PROCESS_ENDED => [
                ['flushDoctrineLogs', 100],
                ['success', 100],
            ],
            ProcessEvent::EVENT_PROCESS_FAILED => [
                ['flushDoctrineLogs', 100],
                ['fail', 100],
            ],
        ];
    }
}
