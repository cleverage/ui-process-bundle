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

use CleverAge\UiProcessBundle\Twig\Runtime\LogLevelExtensionRuntime;
use Twig\DeprecatedCallableInfo;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * @internal
 */
class LogLevelExtension extends AbstractExtension
{
    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('cleverage_ui_process_log_label', [LogLevelExtensionRuntime::class, 'getLabel']),
            new TwigFunction('cleverage_ui_process_log_translation', [LogLevelExtensionRuntime::class, 'getTranslation']),
            new TwigFunction('cleverage_ui_process_log_css_class', [LogLevelExtensionRuntime::class, 'getCssClass']),
            // Former names, removed in 4.0
            new TwigFunction('log_label', [LogLevelExtensionRuntime::class, 'getLabel'], ['deprecation_info' => new DeprecatedCallableInfo('cleverage/ui-process-bundle', '3.3', 'cleverage_ui_process_log_label')]),
            new TwigFunction('log_translation', [LogLevelExtensionRuntime::class, 'getTranslation'], ['deprecation_info' => new DeprecatedCallableInfo('cleverage/ui-process-bundle', '3.3', 'cleverage_ui_process_log_translation')]),
            new TwigFunction('log_css_class', [LogLevelExtensionRuntime::class, 'getCssClass'], ['deprecation_info' => new DeprecatedCallableInfo('cleverage/ui-process-bundle', '3.3', 'cleverage_ui_process_log_css_class')]),
        ];
    }
}
