<?php

namespace CleverAge\ProcessUiBundle\EventSubscriber;

use CleverAge\ProcessBundle\Event\ProcessEvent;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\ProcessUiBundle\Entity\Process;
use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use CleverAge\ProcessUiBundle\Manager\ProcessUiConfigurationManager;
use CleverAge\ProcessUiBundle\Message\LogIndexerMessage;
use CleverAge\ProcessUiBundle\Monolog\Handler\ProcessLogHandler;
use CleverAge\ProcessUiBundle\Repository\ProcessRepository;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ProcessEventSubscriber implements EventSubscriberInterface
{
    protected ?ProcessExecution $processExecution = null;
    protected EntityManagerInterface $entityManager;
    protected ProcessConfigurationRegistry $processConfigurationRegistry;
    protected ProcessLogHandler $processLogHandler;
    protected MessageBusInterface $messageBus;
    protected ProcessUiConfigurationManager $processUiConfigurationManager;
    protected string $processLogDir;

    protected bool $indexLogs;

    public function __construct(
        EntityManagerInterface $entityManager,
        ProcessConfigurationRegistry $processConfigurationRegistry,
        ProcessLogHandler $processLogHandler,
        MessageBusInterface $messageBus,
        ProcessUiConfigurationManager $processUiConfigurationManager,
        string $processLogDir,
        bool $indexLogs
    ) {
        $this->entityManager = $entityManager;
        $this->processConfigurationRegistry = $processConfigurationRegistry;
        $this->processLogHandler = $processLogHandler;
        $this->messageBus = $messageBus;
        $this->processUiConfigurationManager = $processUiConfigurationManager;
        $this->processLogDir = $processLogDir;
        $this->indexLogs = $indexLogs;
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
            ]
        ];
    }

    public function onProcessStarted(ProcessEvent $event): void
    {
        $process = $this->entityManager->getRepository(Process::class)
            ->findOneBy(['processCode' => $event->getProcessCode()]);
        if (null === $process) {
            throw new RuntimeException("Unable to found process into database.");
        }
        $this->processExecution = new ProcessExecution($process);
        $this->processExecution->setProcessCode($event->getProcessCode());
        $this->processExecution->setSource($this->processUiConfigurationManager->getSource($event->getProcessCode()));
        $this->processExecution->setTarget($this->processUiConfigurationManager->getTarget($event->getProcessCode()));
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
            $this->processExecution->getProcess()->setLastExecutionDate($this->processExecution->getStartDate());
            $this->processExecution->getProcess()->setLastExecutionStatus(
                ProcessExecution::STATUS_SUCCESS
            );
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
            $this->processExecution->getProcess()->setLastExecutionDate($this->processExecution->getStartDate());
            $this->processExecution->getProcess()->setLastExecutionStatus(ProcessExecution::STATUS_FAIL);
            $this->entityManager->persist($this->processExecution);
            $this->entityManager->flush();
            $this->dispatchLogIndexerMessage($this->processExecution);
            $this->processExecution = null;
        }
    }

    public function syncProcessIntoDatabase(ProcessEvent $event): void
    {
        /** @var ProcessRepository $repository */
        $repository = $this->entityManager->getRepository(Process::class);
        $repository->sync();
    }

    protected function dispatchLogIndexerMessage(ProcessExecution $processExecution): void
    {
        if ($this->indexLogs && null !== $processExecutionId = $processExecution->getId()) {
            $filePath = $this->processLogDir . DIRECTORY_SEPARATOR . $processExecution->getLog();
            $file     = new \SplFileObject($filePath);
            $file->seek(PHP_INT_MAX);
            $chunkSize = LogIndexerMessage::DEFAULT_OFFSET;
            $chunk     = (int)($file->key() / $chunkSize) + 1;
            for ($i = 0; $i < $chunk; $i++) {
                $this->messageBus->dispatch(
                    new LogIndexerMessage(
                        $processExecutionId,
                        $this->processLogDir . DIRECTORY_SEPARATOR . $processExecution->getLog(),
                        $i * $chunkSize
                    )
                );
            }
        }
    }
}
