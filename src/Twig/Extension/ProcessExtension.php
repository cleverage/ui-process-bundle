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
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ProcessExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'resolve_ui_options',
                [ProcessExtensionRuntime::class, 'getUiOptions']
            ),
        ];
    }
}
