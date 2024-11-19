<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Twig\Extension;

use CleverAge\ProcessUiBundle\Twig\Runtime\ProcessExecutionExtensionRuntime;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ProcessExecutionExtension extends AbstractExtension
{
    public function getFunctions(): array
    {
        return [
            new TwigFunction(
                'get_last_execution_date',
                [ProcessExecutionExtensionRuntime::class, 'getLastExecutionDate']
            ),
            new TwigFunction(
                'get_process_source',
                [ProcessExecutionExtensionRuntime::class, 'getProcessSource']
            ),
            new TwigFunction(
                'get_process_target',
                [ProcessExecutionExtensionRuntime::class, 'getProcessTarget']
            ),
        ];
    }
}
