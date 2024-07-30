<?php
declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Manager;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;

final readonly class ProcessConfigurationsManager
{
    public function __construct(private ProcessConfigurationRegistry $registry)
    {
    }

    /** @return ProcessConfiguration[] */
    public function getPublicProcesses(): array
    {
        return array_filter($this->getConfigurations(), fn(ProcessConfiguration $cfg) => $cfg->isPublic());
    }

    /** @return ProcessConfiguration[] */
    public function getPrivateProcesses(): array
    {
        return array_filter($this->getConfigurations(), fn(ProcessConfiguration $cfg) => !$cfg->isPublic());
    }

    /** @return ProcessConfiguration[] */
    private function getConfigurations(): array
    {
        return $this->registry->getProcessConfigurations();
    }
}
