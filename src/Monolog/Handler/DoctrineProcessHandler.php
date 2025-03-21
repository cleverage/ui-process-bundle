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

namespace CleverAge\UiProcessBundle\Monolog\Handler;

use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Level;
use Monolog\LogRecord;

class DoctrineProcessHandler extends AbstractProcessingHandler
{
    /** @var ArrayCollection<int, LogRecord> */
    private ArrayCollection $records;
    private ?EntityManagerInterface $em = null;
    private ?ProcessExecutionManager $processExecutionManager = null;
    private bool $enabled = true;

    public function __construct(int|string|Level $level = Level::Debug, bool $bubble = true)
    {
        parent::__construct($level, $bubble);
        $this->records = new ArrayCollection();
    }

    public function setEntityManager(EntityManagerInterface $em): void
    {
        $this->em = $em;
    }

    public function setProcessExecutionManager(ProcessExecutionManager $processExecutionManager): void
    {
        $this->processExecutionManager = $processExecutionManager;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    public function __destruct()
    {
        $this->flush();
        parent::__destruct();
    }

    public function flush(): void
    {
        if (!$this->enabled) {
            return;
        }
        foreach ($this->records as $record) {
            if (($currentProcessExecution = $this->processExecutionManager?->getCurrentProcessExecution()) instanceof ProcessExecution) {
                $entity = new \CleverAge\UiProcessBundle\Entity\LogRecord($record, $currentProcessExecution);
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
        if (!$this->enabled) {
            return;
        }
        $this->records->add($record);
        if (500 === $this->records->count()) {
            $this->flush();
        }
    }
}
