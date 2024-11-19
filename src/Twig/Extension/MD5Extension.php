<?php

declare(strict_types=1);

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
