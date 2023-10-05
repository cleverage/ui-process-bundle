<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Twig\Runtime;

use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use CleverAge\ProcessUiBundle\Repository\ProcessExecutionRepository;
use Twig\Extension\RuntimeExtensionInterface;

readonly class ProcessExecutionExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ProcessExecutionRepository $processExecutionRepository,
        private ProcessConfigurationRegistry $processConfigurationRegistry
    )
    {
    }

    public function getLastExecutionDate(string $code): ?ProcessExecution
    {
        return $this->processExecutionRepository->getLastProcessExecution($code);
    }

    public function getProcessSource(string $code): ?string
    {
        return $this->processConfigurationRegistry
            ->getProcessConfiguration($code)?->getOptions()['ui']['source'] ?? null;
    }

    public function getProcessTarget(string $code): ?string
    {
        return $this->processConfigurationRegistry
            ->getProcessConfiguration($code)?->getOptions()['ui']['target'] ?? null;
    }
}
