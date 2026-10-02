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
            static fn (TwigFunction $function): array => [$function->getName(), $function->getCallable()],
            (new ProcessExecutionExtension())->getFunctions()
        );

        self::assertSame([
            ['get_last_execution_date', [ProcessExecutionExtensionRuntime::class, 'getLastExecutionDate']],
            ['get_process_source', [ProcessExecutionExtensionRuntime::class, 'getProcessSource']],
            ['get_process_target', [ProcessExecutionExtensionRuntime::class, 'getProcessTarget']],
        ], $functions);
    }
}
