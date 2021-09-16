<?php

namespace CleverAge\ProcessUiBundle\EventSubscriber;

use CleverAge\ProcessBundle\Event\ProcessEvent;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use CleverAge\ProcessUiBundle\Message\LogIndexerMessage;
use CleverAge\ProcessUiBundle\Monolog\Handler\ProcessLogHandler;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ProcessEventSubscriber implements EventSubscriberInterface
{
    protected ?ProcessExecution $processExecution;

    protected EntityManagerInterface $entityManager;

    protected ProcessConfigurationRegistry $processConfigurationRegistry;

    protected ProcessLogHandler $processLogHandler;

    protected MessageBusInterface $messageBus;

    protected string $processLogDir;

    protected bool $indexLogs;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProcessConfigurationRegistry $processConfigurationRegistry,
        ProcessLogHandler $processLogHandler,
        MessageBusInterface $messageBus,
        string $processLogDir,
        bool $indexLogs
    ) {
        $this->entityManager = $entityManager;
        $this->processConfigurationRegistry = $processConfigurationRegistry;
        $this->processLogHandler = $processLogHandler;
        $this->messageBus = $messageBus;
        $this->processLogDir = $processLogDir;
        $this->indexLogs = $indexLogs;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProcessEvent::EVENT_PROCESS_STARTED => [
                ['onProcessStarted'],
            ],
            ProcessEvent::EVENT_PROCESS_ENDED => [
                ['onProcessEnded'],
            ],
            ProcessEvent::EVENT_PROCESS_FAILED => [
                ['onProcessFailed'],
            ]
        ];
    }

    public function onProcessStarted(ProcessEvent $event): void
    {
        $configuration = $this->processConfigurationRegistry->getProcessConfiguration($event->getProcessCode());
        $this->processExecution = new ProcessExecution();
        $this->processExecution->setProcessCode($configuration->getOptions()['label'] ?? $event->getProcessCode());
        $this->processExecution->setSource($configuration->getOptions()['source'] ?? null);
        $this->processExecution->setTarget($configuration->getOptions()['target'] ?? null);
        $logFilename =  sprintf(
            'process_%s_%s.log',
            $event->getProcessCode(),
            sha1(uniqid((string)mt_rand(), true))
        );
        $this->processLogHandler->setLogFilename($logFilename);
        $this->processExecution->setLog($logFilename);
        $this->entityManager->persist($this->processExecution);
        $this->entityManager->flush();
    }

    public function onProcessEnded(ProcessEvent $event): void
    {
        if ($this->processExecution) {
            $this->processExecution->setEndDate(new \DateTime());
            $this->processExecution->setStatus(ProcessExecution::STATUS_SUCCESS);
            $this->entityManager->persist($this->processExecution);
            $this->entityManager->flush();
            $this->dispatchLogIndexerMessage($this->processExecution);
            $this->processExecution = null;
        }
    }

    public function onProcessFailed(ProcessEvent $event): void
    {
        if ($this->processExecution) {
            $this->processExecution->setEndDate(new \DateTime());
            $this->processExecution->setStatus(ProcessExecution::STATUS_FAIL);
            $this->entityManager->persist($this->processExecution);
            $this->entityManager->flush();
            $this->dispatchLogIndexerMessage($this->processExecution);
            $this->processExecution = null;
        }
    }

    protected function dispatchLogIndexerMessage(ProcessExecution $processExecution): void
    {
        if ($this->indexLogs) {
            $filePath = $this->processLogDir . DIRECTORY_SEPARATOR . $processExecution->getLog();
            $file     = new \SplFileObject($filePath);
            $file->seek(PHP_INT_MAX);
            $chunkSize = LogIndexerMessage::DEFAULT_OFFSET;
            $chunk     = (int)($file->key() / $chunkSize) + 1;
            for ($i = 0; $i < $chunk; $i++) {
                $this->messageBus->dispatch(
                    new LogIndexerMessage(
                        $processExecution->getId(),
                        $this->processLogDir . DIRECTORY_SEPARATOR . $processExecution->getLog(),
                        $i * $chunkSize
                    )
                );
            }
        }
    }
}
