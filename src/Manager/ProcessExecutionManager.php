<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Manager;

use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use CleverAge\ProcessUiBundle\Repository\ProcessExecutionRepository;

class ProcessExecutionManager
{
    private ?ProcessExecution $currentProcessExecution = null;

    public function __construct(private readonly ProcessExecutionRepository $processExecutionRepository)
    {
    }

    public function setCurrentProcessExecution(ProcessExecution $processExecution): self
    {
        if (null === $this->currentProcessExecution) {
            $this->currentProcessExecution = $processExecution;
        }

        return $this;
    }

    public function getCurrentProcessExecution(): ?ProcessExecution
    {
        return $this->currentProcessExecution;
    }

    public function unsetProcessExecution(string $processCode): self
    {
        if ($this->currentProcessExecution?->code === $processCode) {
            $this->currentProcessExecution = null;
        }

        return $this;
    }

    public function save(): self
    {
        if (null !== $this->currentProcessExecution) {
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
