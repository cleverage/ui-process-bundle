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

namespace CleverAge\ProcessUiBundle\EventSubscriber;

use CleverAge\ProcessBundle\Event\ProcessEvent;
use CleverAge\ProcessUiBundle\Entity\Process;
use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use CleverAge\ProcessUiBundle\Event\IncrementReportInfoEvent;
use CleverAge\ProcessUiBundle\Event\SetReportInfoEvent;
use CleverAge\ProcessUiBundle\Manager\ProcessUiConfigurationManager;
use CleverAge\ProcessUiBundle\Message\LogIndexerMessage;
use CleverAge\ProcessUiBundle\Monolog\Handler\ProcessLogHandler;
use CleverAge\ProcessUiBundle\Repository\ProcessRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ProcessEventSubscriber implements EventSubscriberInterface
{
    private array $processExecution = [];

    public function __construct(private readonly EntityManagerInterface $entityManager, private readonly ProcessLogHandler $processLogHandler, private readonly MessageBusInterface $messageBus, private readonly ProcessUiConfigurationManager $processUiConfigurationManager, private readonly string $processLogDir, private readonly bool $indexLogs)
    {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProcessEvent::EVENT_PROCESS_STARTED => [
                ['syncProcessIntoDatabase', 1000],
                ['onProcessStarted', 0],
            ],
            ProcessEvent::EVENT_PROCESS_ENDED => [
                ['onProcessEnded'],
            ],
            ProcessEvent::EVENT_PROCESS_FAILED => [
                ['onProcessFailed'],
            ],
            IncrementReportInfoEvent::NAME => [
                ['updateProcessExecutionReport'],
            ],
            SetReportInfoEvent::NAME => [
                ['updateProcessExecutionReport'],
            ],
        ];
    }

    public function onProcessStarted(ProcessEvent $event): void
    {
        $process = $this->entityManager->getRepository(Process::class)
            ->findOneBy(['processCode' => $event->getProcessCode()]);
        if (null === $process) {
            throw new \RuntimeException('Unable to found process into database.');
        }
        $processExecution = new ProcessExecution($process);
        $processExecution->setProcessCode($event->getProcessCode());
        $processExecution->setSource($this->processUiConfigurationManager->getSource($event->getProcessCode()));
        $processExecution->setTarget($this->processUiConfigurationManager->getTarget($event->getProcessCode()));
        $logFilename = \sprintf(
            'process_%s_%s.log',
            $event->getProcessCode(),
            sha1(uniqid((string) mt_rand(), true))
        );
        $this->processLogHandler->setLogFilename($logFilename, $event->getProcessCode());
        $this->processLogHandler->setCurrentProcessCode($event->getProcessCode());
        $processExecution->setLog($logFilename);
        $this->entityManager->persist($processExecution);
        $this->entityManager->flush();
        $this->processExecution[$event->getProcessCode()] = $processExecution;
    }

    public function onProcessEnded(ProcessEvent $processEvent): void
    {
        if ($processExecution = ($this->processExecution[$processEvent->getProcessCode()] ?? null)) {
            $this->processExecution = array_filter($this->processExecution);
            array_pop($this->processExecution);
            $this->processLogHandler->setCurrentProcessCode((string) array_key_last($this->processExecution));
            $processExecution->setEndDate(new \DateTime());
            $processExecution->setStatus(ProcessExecution::STATUS_SUCCESS);
            $processExecution->getProcess()->setLastExecutionDate($processExecution->getStartDate());
            $processExecution->getProcess()->setLastExecutionStatus(
                ProcessExecution::STATUS_SUCCESS
            );
            $this->entityManager->persist($processExecution);
            $this->entityManager->flush();
            $this->dispatchLogIndexerMessage($processExecution);
            $this->processExecution[$processEvent->getProcessCode()] = null;
        }
    }

    public function onProcessFailed(ProcessEvent $processEvent): void
    {
        if ($processExecution = ($this->processExecution[$processEvent->getProcessCode()] ?? null)) {
            $processExecution->setEndDate(new \DateTime());
            $processExecution->setStatus(ProcessExecution::STATUS_FAIL);
            $processExecution->getProcess()->setLastExecutionDate($processExecution->getStartDate());
            $processExecution->getProcess()->setLastExecutionStatus(ProcessExecution::STATUS_FAIL);
            $this->entityManager->persist($processExecution);
            $this->entityManager->flush();
            $this->dispatchLogIndexerMessage($processExecution);
            $this->processExecution[$processEvent->getProcessCode()] = null;
        }
    }

    public function syncProcessIntoDatabase(): void
    {
        /** @var ProcessRepository $repository */
        $repository = $this->entityManager->getRepository(Process::class);
        $repository->sync();
    }

    protected function dispatchLogIndexerMessage(ProcessExecution $processExecution): void
    {
        if ($this->indexLogs && null !== $processExecutionId = $processExecution->getId()) {
            $filePath = $this->processLogDir.\DIRECTORY_SEPARATOR.$processExecution->getLog();
            $file = new \SplFileObject($filePath);
            $file->seek(\PHP_INT_MAX);
            $chunkSize = LogIndexerMessage::DEFAULT_OFFSET;
            $chunk = (int) ($file->key() / $chunkSize) + 1;
            for ($i = 0; $i < $chunk; ++$i) {
                $this->messageBus->dispatch(
                    new LogIndexerMessage(
                        $processExecutionId,
                        $this->processLogDir.\DIRECTORY_SEPARATOR.$processExecution->getLog(),
                        $i * $chunkSize
                    )
                );
            }
        }
    }

    public function updateProcessExecutionReport(IncrementReportInfoEvent|SetReportInfoEvent $event): void
    {
        if ($processExecution = ($this->processExecution[$event->getProcessCode()] ?? false)) {
            $report = $processExecution->getReport();
            $event instanceof IncrementReportInfoEvent
                ? $report[$event->getKey()] = ($report[$event->getKey()] ?? 0) + 1
                : $report[$event->getKey()] = $event->getValue();
            $processExecution->setReport($report);
        }
    }
}
