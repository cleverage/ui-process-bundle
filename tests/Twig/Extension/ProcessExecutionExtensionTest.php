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

namespace CleverAge\UiProcessBundle\Tests\Twig\Extension;

use CleverAge\UiProcessBundle\Twig\Extension\ProcessExecutionExtension;
use CleverAge\UiProcessBundle\Twig\Runtime\ProcessExecutionExtensionRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

#[CoversClass(ProcessExecutionExtension::class)]
class ProcessExecutionExtensionTest extends TestCase
{
    public function testGetFunctions(): void
    {
        $functions = array_map(
            static fn (TwigFunction $function): array => [$function->getName(), $function->getCallable(), $function->isDeprecated()],
            (new ProcessExecutionExtension())->getFunctions()
        );

        // Prefixed names, and the former ones, deprecated
        self::assertSame([
            ['cleverage_ui_process_get_last_execution_date', [ProcessExecutionExtensionRuntime::class, 'getLastExecutionDate'], false],
            ['cleverage_ui_process_get_process_source', [ProcessExecutionExtensionRuntime::class, 'getProcessSource'], false],
            ['cleverage_ui_process_get_process_target', [ProcessExecutionExtensionRuntime::class, 'getProcessTarget'], false],
            ['get_last_execution_date', [ProcessExecutionExtensionRuntime::class, 'getLastExecutionDate'], true],
            ['get_process_source', [ProcessExecutionExtensionRuntime::class, 'getProcessSource'], true],
            ['get_process_target', [ProcessExecutionExtensionRuntime::class, 'getProcessTarget'], true],
        ], $functions);
    }

    public function testFormerNamesAreDeprecated(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            foreach ((new ProcessExecutionExtension())->getFunctions() as $function) {
                if ($function->isDeprecated()) {
                    $function->triggerDeprecation();
                }
            }
        } finally {
            restore_error_handler();
        }

        self::assertSame([
            'Since cleverage/ui-process-bundle 3.3: Twig Function "get_last_execution_date" is deprecated; use "cleverage_ui_process_get_last_execution_date" instead.',
            'Since cleverage/ui-process-bundle 3.3: Twig Function "get_process_source" is deprecated; use "cleverage_ui_process_get_process_source" instead.',
            'Since cleverage/ui-process-bundle 3.3: Twig Function "get_process_target" is deprecated; use "cleverage_ui_process_get_process_target" instead.',
        ], $deprecations);
    }
}
