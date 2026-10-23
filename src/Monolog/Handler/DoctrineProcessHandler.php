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
use Psr\Log\LoggerInterface;

/**
 * @internal
 */
class DoctrineProcessHandler extends AbstractProcessingHandler
{
    /** @var ArrayCollection<int, LogRecord> */
    private ArrayCollection $records;
    private ?EntityManagerInterface $em = null;
    private ?ProcessExecutionManager $processExecutionManager = null;
    private bool $enabled = true;
    private ?LoggerInterface $logger = null;
    private bool $reportingFailure = false;

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

    /**
     * Logger reporting the failures to store the logs in database (the log records are dropped, the process goes on).
     */
    public function setLogger(?LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    #[\Override]
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
        if ($this->records->isEmpty() && false === $this->em?->isOpen()) {
            // Nothing to write, and a closed entity manager cannot be flushed (it is reset when writing the next logs
            // or saving the process execution, see ProcessExecutionRepository::getManaged())
            return;
        }
        if ($this->records->isEmpty()) {
            // Writes the changes pending in the entity manager, shared with the process tasks: their errors are not
            // caught
            $this->em?->flush();

            return;
        }
        // Dropped even if their writing fails: they would be written again, and fail again, with the next ones
        $records = $this->records;
        $this->records = new ArrayCollection();
        $entities = [];
        try {
            $currentProcessExecution = $this->processExecutionManager?->getManagedProcessExecution();
            foreach ($records as $record) {
                if ($currentProcessExecution instanceof ProcessExecution) {
                    $entity = new \CleverAge\UiProcessBundle\Entity\LogRecord($record, $currentProcessExecution);
                    $this->em?->persist($entity);
                    $entities[] = $entity;
                }
            }
        } catch (\Throwable $exception) {
            $this->onWriteFailure($exception, $records->count(), $entities);

            return;
        }
        $onlyLogs = $this->hasOnlyLogsToWrite($currentProcessExecution, $entities);
        try {
            $this->em?->flush();
        } catch (\Throwable $exception) {
            if (!$onlyLogs) {
                // The entity manager is shared with the process tasks: the failure may come from their entities
                throw $exception;
            }
            // The logs must neither make the process fail nor prevent saving the status of its execution
            $this->onWriteFailure($exception, $records->count(), $entities);

            return;
        }
        // Written log records are no longer needed: detached so that the identity map does not grow during long
        // processes (detach is not cascaded to the process execution, see LogRecord::$processExecution)
        foreach ($entities as $entity) {
            $this->em?->detach($entity);
        }
    }

    /**
     * Drop the records not written at the end of the previous process execution (logged after its last flush), e.g.
     * between two messages of a long-running worker: they would be attached to the next one.
     */
    #[\Override]
    public function reset(): void
    {
        parent::reset();
        $this->records = new ArrayCollection();
    }

    protected function write(LogRecord $record): void
    {
        if (!$this->enabled) {
            return;
        }
        if ($this->reportingFailure) {
            // The failure of the writing is not stored in database
            return;
        }
        $this->records->add($record);
        if ($this->records->count() >= 500) {
            $this->flush();
        }
    }

    /**
     * Only the log records and the current process execution are to be written by the flush: no change of the entities
     * of the process tasks, sharing the entity manager (an error of the computation of the changes, e.g. a new entity
     * not persisted, is not caught).
     *
     * @param list<\CleverAge\UiProcessBundle\Entity\LogRecord> $entities
     */
    private function hasOnlyLogsToWrite(?ProcessExecution $currentProcessExecution, array $entities): bool
    {
        if (!$this->em instanceof EntityManagerInterface) {
            return true;
        }
        $ownEntities = [];
        foreach ([...$entities, $currentProcessExecution] as $entity) {
            if (null !== $entity) {
                $ownEntities[spl_object_id($entity)] = true;
            }
        }
        $isOwnEntity = static fn (object $entity): bool => isset($ownEntities[spl_object_id($entity)]);
        $unitOfWork = $this->em->getUnitOfWork();
        // Known without computing the changes
        foreach ($unitOfWork->getScheduledEntityInsertions() as $entity) {
            if (!$isOwnEntity($entity)) {
                return false;
            }
        }
        if ([] !== $unitOfWork->getScheduledEntityDeletions() || [] !== $unitOfWork->getScheduledCollectionDeletions()) {
            return false;
        }
        $onlyOwnEntitiesManaged = true;
        foreach ($unitOfWork->getIdentityMap() as $managedEntities) {
            foreach ($managedEntities as $entity) {
                if (!$isOwnEntity($entity)) {
                    $onlyOwnEntitiesManaged = false;
                    break 2;
                }
            }
        }
        if ($onlyOwnEntitiesManaged) {
            // No entity of the tasks to update
            return true;
        }
        // The updates of the managed entities are known once the changes computed (computed again by the flush)
        $unitOfWork->computeChangeSets();
        foreach ($unitOfWork->getScheduledEntityUpdates() as $entity) {
            if (!$isOwnEntity($entity)) {
                return false;
            }
        }

        return [] === $unitOfWork->getScheduledCollectionUpdates() && [] === $unitOfWork->getScheduledCollectionDeletions();
    }

    /**
     * @param list<\CleverAge\UiProcessBundle\Entity\LogRecord> $entities
     */
    private function onWriteFailure(\Throwable $exception, int $count, array $entities): void
    {
        $this->reportingFailure = true;
        try {
            $this->logger?->error(
                \sprintf('%d log record(s) could not be stored in database: %s', $count, $exception->getMessage()),
                ['exception' => $exception]
            );
        } finally {
            $this->reportingFailure = false;
        }
        if (true === $this->em?->isOpen()) {
            // Not inserted by the next flush, e.g. when saving the process execution
            foreach ($entities as $entity) {
                $this->em->detach($entity);
            }

            return;
        }
        try {
            // Closed by Doctrine after the failure of the flush: reset right away for the process tasks (see
            // ProcessExecutionRepository::getManaged())
            $this->processExecutionManager?->getManagedProcessExecution();
        } catch (\Throwable) {
            // E.g. the database is still unavailable: reset again before the next writing
        }
    }
}
