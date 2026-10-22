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

use CleverAge\UiProcessBundle\Twig\Extension\ProcessExtension;
use CleverAge\UiProcessBundle\Twig\Runtime\ProcessExtensionRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

#[CoversClass(ProcessExtension::class)]
class ProcessExtensionTest extends TestCase
{
    public function testGetFunctions(): void
    {
        $functions = array_map(
            static fn (TwigFunction $function): array => [$function->getName(), $function->getCallable(), $function->isDeprecated()],
            (new ProcessExtension())->getFunctions()
        );

        // Prefixed names, and the former ones, deprecated
        self::assertSame([
            ['cleverage_ui_process_resolve_ui_options', [ProcessExtensionRuntime::class, 'getUiOptions'], false],
            ['resolve_ui_options', [ProcessExtensionRuntime::class, 'getUiOptions'], true],
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
            foreach ((new ProcessExtension())->getFunctions() as $function) {
                if ($function->isDeprecated()) {
                    $function->triggerDeprecation();
                }
            }
        } finally {
            restore_error_handler();
        }

        self::assertSame([
            'Since cleverage/ui-process-bundle 3.3: Twig Function "resolve_ui_options" is deprecated; use "cleverage_ui_process_resolve_ui_options" instead.',
        ], $deprecations);
    }
}
