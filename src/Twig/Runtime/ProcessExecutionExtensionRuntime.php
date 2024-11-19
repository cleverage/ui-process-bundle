<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Twig\Runtime;

use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use CleverAge\ProcessUiBundle\Manager\ProcessConfigurationsManager;
use CleverAge\ProcessUiBundle\Repository\ProcessExecutionRepository;
use Twig\Extension\RuntimeExtensionInterface;

readonly class ProcessExecutionExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ProcessExecutionRepository $processExecutionRepository,
        private ProcessConfigurationsManager $processConfigurationsManager,
    ) {
    }

    public function getLastExecutionDate(string $code): ?ProcessExecution
    {
        return $this->processExecutionRepository->getLastProcessExecution($code);
    }

    public function getProcessSource(string $code): ?string
    {
        return $this->processConfigurationsManager->getUiOptions($code)['source'];
    }

    public function getProcessTarget(string $code): ?string
    {
        return $this->processConfigurationsManager->getUiOptions($code)['target'];
    }
}
