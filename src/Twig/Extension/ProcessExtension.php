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

use CleverAge\UiProcessBundle\Twig\Runtime\ProcessExtensionRuntime;
use Twig\DeprecatedCallableInfo;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
class ProcessExtension extends AbstractExtension
{
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cleverage_ui_process_resolve_ui_options', [ProcessExtensionRuntime::class, 'getUiOptions']),
            // Former names, removed in 4.0
            new TwigFunction('resolve_ui_options', [ProcessExtensionRuntime::class, 'getUiOptions'], ['deprecation_info' => new DeprecatedCallableInfo('cleverage/ui-process-bundle', '3.3', 'cleverage_ui_process_resolve_ui_options')]),
        ];
    }
}
