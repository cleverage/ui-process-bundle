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

namespace CleverAge\ProcessUiBundle\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class MD5ExtensionRuntime implements RuntimeExtensionInterface
{
    public function md5(string $value): string
    {
        return md5($value);
    }
}
