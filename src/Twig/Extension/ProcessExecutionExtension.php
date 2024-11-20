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

namespace CleverAge\ProcessUiBundle\Twig\Extension;

use CleverAge\ProcessUiBundle\Twig\Runtime\ProcessExecutionExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ProcessExecutionExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'get_last_execution_date',
                [ProcessExecutionExtensionRuntime::class, 'getLastExecutionDate']
            ),
            new TwigFunction(
                'get_process_source',
                [ProcessExecutionExtensionRuntime::class, 'getProcessSource']
            ),
            new TwigFunction(
                'get_process_target',
                [ProcessExecutionExtensionRuntime::class, 'getProcessTarget']
            ),
        ];
    }
}
