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

namespace CleverAge\UiProcessBundle\Twig\Runtime;

use CleverAge\UiProcessBundle\Entity\ProcessExecutionInterface;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepositoryInterface;
use Twig\Extension\RuntimeExtensionInterface;

readonly class ProcessExecutionExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ProcessExecutionRepositoryInterface $processExecutionRepository,
        private ProcessConfigurationsManager $processConfigurationsManager,
    ) {
    }

    public function getLastExecutionDate(string $code): ?ProcessExecutionInterface
    {
        return $this->processExecutionRepository->getLastProcessExecution($code);
    }

    public function getProcessSource(string $code): ?string
    {
        return $this->processConfigurationsManager->getUiOptions($code)['source'] ?? null;
    }

    public function getProcessTarget(string $code): ?string
    {
        return $this->processConfigurationsManager->getUiOptions($code)['target'] ?? null;
    }
}
