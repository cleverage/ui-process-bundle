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

use CleverAge\UiProcessBundle\Entity\ProcessExecutionInterface;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepositoryInterface;

class ProcessExecutionManager
{
    private ?ProcessExecutionInterface $currentProcessExecution = null;

    public function __construct(private readonly ProcessExecutionRepositoryInterface $processExecutionRepository)
    {
    }

    public function setCurrentProcessExecution(ProcessExecutionInterface $processExecution): self
    {
        if (!$this->currentProcessExecution instanceof ProcessExecutionInterface) {
            $this->currentProcessExecution = $processExecution;
        }

        return $this;
    }

    public function getCurrentProcessExecution(): ?ProcessExecutionInterface
    {
        return $this->currentProcessExecution;
    }

    public function unsetProcessExecution(string $processCode): self
    {
        if ($this->currentProcessExecution?->getCode() === $processCode) {
            $this->currentProcessExecution = null;
        }

        return $this;
    }

    public function save(): self
    {
        if ($this->currentProcessExecution instanceof ProcessExecutionInterface) {
            $this->processExecutionRepository->save($this->currentProcessExecution);
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
