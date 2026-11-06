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
    private ?Level $reportIncrementLevel = null;
    private bool $shutdownFunctionRegistered = false;
    private int $writeRightAwayInterval = 5;
    private ?float $lastWriteTime = null;

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

    /**
     * A log record from this level (counted in the report of the process execution) is written right away, with the
     * buffered ones: they are not lost if the process ends with a fatal error which the shutdown function cannot handle
     * (e.g. no memory left, process killed). Below the Error level, at most every $writeRightAwayInterval seconds (see
     * setWriteRightAwayInterval()).
     *
     * @param 'ALERT'|'Alert'|'alert'|'CRITICAL'|'Critical'|'critical'|'DEBUG'|'Debug'|'debug'|'EMERGENCY'|'Emergency'|'emergency'|'ERROR'|'Error'|'error'|'INFO'|'Info'|'info'|'NOTICE'|'Notice'|'notice'|'WARNING'|'Warning'|'warning' $level
     */
    public function setReportIncrementLevel(string $level): void
    {
        $this->reportIncrementLevel = Level::fromName($level);
    }

    /**
     * Minimum number of seconds between the last writing of the log records and the writing right away of a log record
     * counted in the report, below the Error level (0: each one): writing each record of a process logging many
     * warnings, with the report of the process execution, made it several times slower. The records from the Error
     * level are always written right away.
     */
    public function setWriteRightAwayInterval(int $seconds): void
    {
        $this->writeRightAwayInterval = max(0, $seconds);
    }

    public function disable(): void
    {
        $this->enabled = false;
    }

    #[\Override]
    public function __destruct()
    {
        try {
            $this->flush();
        } catch (\Throwable $exception) {
            // An exception thrown by a destructor at the end of the script is a fatal error (e.g. the database driver
            // is missing, the pending changes of the process tasks cannot be written): reported, as a failure of the
            // writing of the log records
            $this->reportingFailure = true;
            try {
                $this->logger?->error(
                    \sprintf('The pending log records and changes could not be written when the handler was destroyed: %s', $exception->getMessage()),
                    ['exception' => $exception]
                );
            } catch (\Throwable) {
                // Nothing left to report it
            } finally {
                $this->reportingFailure = false;
            }
        }
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
        $this->lastWriteTime = microtime(true);
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
        $this->lastWriteTime = null;
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
        $this->registerShutdownFunction();
        $this->records->add($record);
        if ($this->records->count() >= 500 || $this->isToWriteRightAway($record)) {
            $this->flush();
        }
    }

    /**
     * A log record counted in the report, during a process execution, when no change of the process tasks is pending
     * (they would be written with it): checked without computing the changes of their managed entities, it is
     * otherwise written with the next ones. Below the Error level, only when the last writing is at least
     * $writeRightAwayInterval seconds old: it is otherwise written with the next ones.
     */
    private function isToWriteRightAway(LogRecord $record): bool
    {
        if (!$this->reportIncrementLevel instanceof Level || $record->level->value < $this->reportIncrementLevel->value) {
            return false;
        }
        if ($record->level->isLowerThan(Level::Error)
            && null !== $this->lastWriteTime
            && microtime(true) - $this->lastWriteTime < $this->writeRightAwayInterval
        ) {
            return false;
        }
        $currentProcessExecution = $this->processExecutionManager?->getCurrentProcessExecution();

        return $currentProcessExecution instanceof ProcessExecution
            && (false === $this->em?->isOpen() || $this->hasOnlyLogsToWrite($currentProcessExecution, [], false));
    }

    /**
     * The memory used when the memory limit was reached is still allocated on shutdown: the limit is raised by 32 MiB
     * to write the log records. The shutdown function of the Symfony ErrorHandler, registered before, usually fails too
     * (no memory left to load a class): the next shutdown functions are then not called.
     */
    private function allowMemoryAfterMemoryLimit(): void
    {
        $error = error_get_last();
        if (null === $error || !str_starts_with($error['message'], 'Allowed memory size of') || '-1' === \ini_get('memory_limit')) {
            return;
        }
        ini_set('memory_limit', (string) (memory_get_usage(true) + 32 * 1024 * 1024));
    }

    /**
     * Registered once, without keeping the handler alive (it is still destructed, and flushed, when no longer used).
     */
    private function registerShutdownFunction(): void
    {
        if ($this->shutdownFunctionRegistered) {
            return;
        }
        $this->shutdownFunctionRegistered = true;
        $handler = \WeakReference::create($this);
        register_shutdown_function(static function () use ($handler): void {
            $handler->get()?->onShutdown();
        });
    }

    /**
     * After a fatal error (e.g. memory limit, maximum execution time), neither the end of the process nor the destructor
     * write the buffered log records and the report of the current process execution: written on shutdown, if no change
     * of the process tasks is pending (they would be written with them, in an unknown state). Not called after the end
     * of the process: the current process execution is then unset.
     *
     * @internal
     */
    public function onShutdown(): void
    {
        $processExecutionManager = $this->processExecutionManager;
        if (!$this->enabled || !$processExecutionManager?->getCurrentProcessExecution() instanceof ProcessExecution) {
            return;
        }
        $this->allowMemoryAfterMemoryLimit();
        try {
            if (true === $this->em?->isOpen()
                && !$this->hasOnlyLogsToWrite($processExecutionManager->getManagedProcessExecution(), [])
            ) {
                return;
            }
            $this->flush();
        } catch (\Throwable) {
            // Nothing more can be done: the log file has the log records
        }
    }

    /**
     * Only the log records and the current process execution are to be written by the flush: no change of the entities
     * of the process tasks, sharing the entity manager (an error of the computation of the changes, e.g. a new entity
     * not persisted, is not caught).
     *
     * @param list<\CleverAge\UiProcessBundle\Entity\LogRecord> $entities
     * @param bool                                              $computeChanges false: false when entities of the
     *                                                                          tasks are managed, without computing their
     *                                                                          changes (cost proportional to their number)
     */
    private function hasOnlyLogsToWrite(?ProcessExecution $currentProcessExecution, array $entities, bool $computeChanges = true): bool
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
        if (!$computeChanges) {
            return false;
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
