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

use CleverAge\ProcessUiBundle\Twig\Runtime\MD5ExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class MD5Extension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('md5', [MD5ExtensionRuntime::class, 'md5']),
        ];
    }
}
