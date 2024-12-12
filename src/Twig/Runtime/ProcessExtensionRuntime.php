<?php

/*
 * This file is part of the CleverAge/UiProcessBundle package.
 *
 * Copyright (c) Clever-Age
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace CleverAge\UiProcessBundle\Twig\Runtime;

use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use Twig\Extension\RuntimeExtensionInterface;

class ProcessExtensionRuntime implements RuntimeExtensionInterface
{
    public function __construct(protected ProcessConfigurationsManager $processConfigurationsManager)
    {
    }

    public function getUiOptions(string $code): array
    {
        return $this->processConfigurationsManager->getUiOptions($code) ?? [];
    }
}
