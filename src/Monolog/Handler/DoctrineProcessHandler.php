<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Monolog\Handler;

use CleverAge\ProcessUiBundle\Manager\ProcessExecutionManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;
use Symfony\Contracts\Service\Attribute\Required;

class DoctrineProcessHandler extends AbstractProcessingHandler
{
    /** @var ArrayCollection<int, LogRecord> */
    private ArrayCollection $records;
    private ?ProcessExecutionManager $processExecutionManager;
    private ?EntityManagerInterface $em = null;

    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->records = new ArrayCollection();
    }

    #[Required]
    public function setEntityManager(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    #[Required]
    public function setProcessExecutionManager(ProcessExecutionManager $processExecutionManager): void
    {
        $this->processExecutionManager = $processExecutionManager;
    }

    public function __destruct()
    {
        $this->flush();
        parent::__destruct();
    }

    public function flush(): void
    {
        foreach ($this->records as $record) {
            if ($currentProcessExecution = $this->processExecutionManager->getCurrentProcessExecution()) {
                $entity = new \CleverAge\ProcessUiBundle\Entity\LogRecord($record, $currentProcessExecution);
                $this->em?->persist($entity);
            }
        }
        $this->em?->flush();
        foreach ($this->records as $record) {
            $this->em?->detach($record);
        }
        $this->records = new ArrayCollection();
    }

    protected function write(LogRecord $record): void
    {
        $this->records->add($record);
        if (500 === $this->records->count()) {
            $this->flush();
        }
    }
}
