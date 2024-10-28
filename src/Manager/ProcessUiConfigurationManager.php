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

namespace CleverAge\ProcessUiBundle\Manager;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\ProcessUiBundle\Entity\Process;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProcessUiConfigurationManager
{
    public const UI_OPTION_SOURCE = 'source';
    public const UI_OPTION_TARGET = 'target';
    public const UI_OPTION_RUN = 'ui_run';

    public function __construct(private readonly ProcessConfigurationRegistry $processConfigurationRegistry)
    {
    }

    /**
     * @return array <string, string>
     */
    public function getProcessChoices(): array
    {
        return array_map(static fn (ProcessConfiguration $configuration) => $configuration->getCode(), $this->processConfigurationRegistry->getProcessConfigurations());
    }

    /**
     * @return array <string, string>
     */
    public function getSourceChoices(): array
    {
        $sources = [];
        foreach ($this->processConfigurationRegistry->getProcessConfigurations() as $configuration) {
            $source = $this->getSource($configuration->getCode());
            $sources[(string) $source] = (string) $source;
        }

        return $sources;
    }

    /**
     * @return array <string, string>
     */
    public function getTargetChoices(): array
    {
        $targets = [];
        foreach ($this->processConfigurationRegistry->getProcessConfigurations() as $configuration) {
            $target = $this->getTarget($configuration->getCode());
            $targets[(string) $target] = (string) $target;
        }

        return $targets;
    }

    public function getSource(Process|string $process): ?string
    {
        return $this->resolveUiOptions($process)[self::UI_OPTION_SOURCE];
    }

    public function getTarget(Process|string $process): ?string
    {
        return $this->resolveUiOptions($process)[self::UI_OPTION_TARGET];
    }

    public function canRun(Process|string $process): bool
    {
        return (bool) $this->resolveUiOptions($process)[self::UI_OPTION_RUN];
    }

    /**
     * @return array <string, string>
     */
    private function resolveUiOptions(Process|string $process): array
    {
        $code = $process instanceof Process ? $process->getProcessCode() : $process;
        $resolver = new OptionsResolver();

        $resolver->setDefaults([
            self::UI_OPTION_SOURCE => null,
            self::UI_OPTION_TARGET => null,
            self::UI_OPTION_RUN => true,
        ]);
        $resolver->setAllowedTypes(self::UI_OPTION_RUN, 'bool');
        $resolver->setAllowedTypes(self::UI_OPTION_SOURCE, ['string', 'null']);
        $resolver->setAllowedTypes(self::UI_OPTION_TARGET, ['string', 'null']);

        return $resolver->resolve(
            $this->processConfigurationRegistry->getProcessConfiguration($code)->getOptions()['ui_options'] ?? []
        );
    }
}
