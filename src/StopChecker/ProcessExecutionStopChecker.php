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

namespace CleverAge\UiProcessBundle\StopChecker;

use CleverAge\ProcessBundle\Model\ProcessState;
use CleverAge\ProcessBundle\Model\ProcessStopCheckerInterface;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Asked by the process manager before each task execution: at most every $heartbeatInterval seconds, updates the
 * heartbeat of the current process execution and reads its stop request (requested from the UI).
 *
 * DBAL queries are used: no ORM query per item, and the entity manager may have been cleared or closed by the process
 * tasks.
 */
class ProcessExecutionStopChecker implements ProcessStopCheckerInterface
{
    private ?int $processExecutionId = null;

    private float $lastCheck = 0.0;

    private bool $stopRequested = false;

    private bool $errorLogged = false;

    public function __construct(
        private readonly ProcessExecutionManager $processExecutionManager,
        private readonly EntityManagerInterface $entityManager,
        private readonly int $heartbeatInterval = 5,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    public function shouldStop(ProcessState $state): bool
    {
        $processExecutionId = $this->processExecutionManager->getCurrentProcessExecution()?->getId();
        if (null === $processExecutionId) {
            return false;
        }
        if ($processExecutionId !== $this->processExecutionId) {
            // A new process execution (long-running worker): check right away
            $this->processExecutionId = $processExecutionId;
            $this->lastCheck = 0.0;
            $this->stopRequested = false;
            $this->errorLogged = false;
        }
        if ($this->stopRequested || microtime(true) - $this->lastCheck < $this->heartbeatInterval) {
            return $this->stopRequested;
        }
        $this->lastCheck = microtime(true);

        try {
            $connection = $this->entityManager->getConnection();
            $connection->update(
                'process_execution',
                ['heartbeat_at' => new \DateTimeImmutable()],
                ['id' => $processExecutionId],
                ['heartbeat_at' => Types::DATETIME_IMMUTABLE]
            );
            $stopRequestedAt = $connection->fetchOne(
                'SELECT stop_requested_at FROM process_execution WHERE id = ?',
                [$processExecutionId]
            );
            $this->stopRequested = null !== $stopRequestedAt && false !== $stopRequestedAt;
        } catch (\Throwable $e) {
            // The process must not fail because of its heartbeat: logged once per process execution
            if (!$this->errorLogged) {
                $this->errorLogged = true;
                $this->logger?->warning('Unable to update the heartbeat of the process execution, nor to read its stop request.', [
                    'process_execution' => $processExecutionId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->stopRequested;
    }
}
