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

namespace CleverAge\UiProcessBundle\Manager;

use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;

class ProcessExecutionManager
{
    private ?ProcessExecution $currentProcessExecution = null;

    private ?ProcessExecution $lastProcessExecution = null;

    public function __construct(private readonly ProcessExecutionRepository $processExecutionRepository)
    {
    }

    public function setCurrentProcessExecution(ProcessExecution $processExecution): self
    {
        if (!$this->currentProcessExecution instanceof ProcessExecution) {
            $this->currentProcessExecution = $processExecution;
        }

        return $this;
    }

    public function getCurrentProcessExecution(): ?ProcessExecution
    {
        return $this->currentProcessExecution;
    }

    /**
     * The current process execution, attached again to the entity manager if it has been detached.
     */
    public function getManagedProcessExecution(): ?ProcessExecution
    {
        if ($this->currentProcessExecution instanceof ProcessExecution) {
            $this->currentProcessExecution = $this->processExecutionRepository->getManaged($this->currentProcessExecution);
        }

        return $this->currentProcessExecution;
    }

    /**
     * The last process execution which has ended (its current process execution is unset at the end of a process).
     */
    public function getLastProcessExecution(): ?ProcessExecution
    {
        return $this->lastProcessExecution;
    }

    public function unsetProcessExecution(string $processCode): self
    {
        if ($this->currentProcessExecution?->code === $processCode) {
            $this->lastProcessExecution = $this->currentProcessExecution;
            $this->currentProcessExecution = null;
        }

        return $this;
    }

    public function save(): self
    {
        if (($processExecution = $this->getManagedProcessExecution()) instanceof ProcessExecution) {
            $this->processExecutionRepository->save($processExecution);
        }

        return $this;
    }

    public function increment(string $incrementKey, int $step = 1): void
    {
        $this->currentProcessExecution?->addReport(
            $incrementKey,
            $this->currentProcessExecution->getReport($incrementKey, 0) + $step
        );
    }

    public function setReport(string $incrementKey, string $value): void
    {
        $this->currentProcessExecution?->addReport($incrementKey, $value);
    }
}
