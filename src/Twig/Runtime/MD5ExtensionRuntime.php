<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Twig\Runtime;

use Twig\Extension\RuntimeExtensionInterface;

class MD5ExtensionRuntime implements RuntimeExtensionInterface
{
    public function md5(string $value): string
    {
        return md5($value);
    }
}
