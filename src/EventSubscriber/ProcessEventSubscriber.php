<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\EventSubscriber;

use CleverAge\ProcessBundle\Event\ProcessEvent;
use CleverAge\ProcessUiBundle\Entity\Enum\ProcessExecutionStatus;
use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use CleverAge\ProcessUiBundle\Manager\ProcessExecutionManager;
use CleverAge\ProcessUiBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\ProcessUiBundle\Monolog\Handler\ProcessHandler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Uid\Uuid;

final readonly class ProcessEventSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private ProcessHandler $processHandler,
        private DoctrineProcessHandler $doctrineProcessHandler,
        private ProcessExecutionManager $processExecutionManager
    ) {
    }

    public function onProcessStart(ProcessEvent $event): void
    {
        if (false === $this->processHandler->hasFilename()) {
            $this->processHandler->setFilename(sprintf('%s/%s.log', $event->getProcessCode(), Uuid::v4()));
        }
        if (null === $this->processExecutionManager->getCurrentProcessExecution()) {
            $processExecution = new ProcessExecution(
                $event->getProcessCode(),
                basename($this->processHandler->getFilename()),
                $event->getProcessContext()
            );
            $this->processExecutionManager->setCurrentProcessExecution($processExecution)->save();
        }
    }

    public function success(ProcessEvent $event): void
    {
        if ($event->getProcessCode() === $this->processExecutionManager?->getCurrentProcessExecution()?->getCode()) {
            $this->processExecutionManager->getCurrentProcessExecution()?->setStatus(ProcessExecutionStatus::Finish);
            $this->processExecutionManager->getCurrentProcessExecution()?->end();
            $this->processExecutionManager->save()->unsetProcessExecution($event->getProcessCode());
            $this->processHandler->close();
        }
    }

    public function fail(ProcessEvent $event): void
    {
        if ($event->getProcessCode() === $this->processExecutionManager?->getCurrentProcessExecution()?->getCode()) {
            $this->processExecutionManager->getCurrentProcessExecution()?->setStatus(ProcessExecutionStatus::Failed);
            $this->processExecutionManager->getCurrentProcessExecution()?->end();
            $this->processExecutionManager->save()->unsetProcessExecution($event->getProcessCode());
            $this->processHandler->close();
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
