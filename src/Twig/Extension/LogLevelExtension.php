<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Twig\Extension;

use CleverAge\ProcessUiBundle\Twig\Runtime\LogLevelExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class LogLevelExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction('log_label', [LogLevelExtensionRuntime::class, 'getLabel']),
            new TwigFunction('log_css_class', [LogLevelExtensionRuntime::class, 'getCssClass']),
        ];
    }
}
