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

namespace CleverAge\UiProcessBundle\Twig\Extension;

use CleverAge\UiProcessBundle\Twig\Runtime\ProcessExecutionExtensionRuntime;
use Twig\DeprecatedCallableInfo;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
class ProcessExecutionExtension extends AbstractExtension
{
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cleverage_ui_process_get_last_execution_date', [ProcessExecutionExtensionRuntime::class, 'getLastExecutionDate']),
            new TwigFunction('cleverage_ui_process_get_process_source', [ProcessExecutionExtensionRuntime::class, 'getProcessSource']),
            new TwigFunction('cleverage_ui_process_get_process_target', [ProcessExecutionExtensionRuntime::class, 'getProcessTarget']),
            // Former names, removed in 4.0
            new TwigFunction('get_last_execution_date', [ProcessExecutionExtensionRuntime::class, 'getLastExecutionDate'], ['deprecation_info' => new DeprecatedCallableInfo('cleverage/ui-process-bundle', '3.3', 'cleverage_ui_process_get_last_execution_date')]),
            new TwigFunction('get_process_source', [ProcessExecutionExtensionRuntime::class, 'getProcessSource'], ['deprecation_info' => new DeprecatedCallableInfo('cleverage/ui-process-bundle', '3.3', 'cleverage_ui_process_get_process_source')]),
            new TwigFunction('get_process_target', [ProcessExecutionExtensionRuntime::class, 'getProcessTarget'], ['deprecation_info' => new DeprecatedCallableInfo('cleverage/ui-process-bundle', '3.3', 'cleverage_ui_process_get_process_target')]),
        ];
    }
}
