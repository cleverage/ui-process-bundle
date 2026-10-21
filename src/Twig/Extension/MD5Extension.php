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

use CleverAge\UiProcessBundle\Twig\Runtime\MD5ExtensionRuntime;
use Twig\DeprecatedCallableInfo;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * @internal
 */
class MD5Extension extends AbstractExtension
{
    #[\Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('cleverage_ui_process_md5', [MD5ExtensionRuntime::class, 'md5']),
            // Former names, removed in 4.0
            new TwigFilter('md5', [MD5ExtensionRuntime::class, 'md5'], ['deprecation_info' => new DeprecatedCallableInfo('cleverage/ui-process-bundle', '3.3', 'cleverage_ui_process_md5')]),
        ];
    }
}
