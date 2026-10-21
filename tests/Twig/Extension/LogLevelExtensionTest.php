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

use CleverAge\UiProcessBundle\Twig\Extension\LogLevelExtension;
use CleverAge\UiProcessBundle\Twig\Runtime\LogLevelExtensionRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\TwigFunction;

#[CoversClass(LogLevelExtension::class)]
class LogLevelExtensionTest extends TestCase
{
    public function testGetFunctions(): void
    {
        $functions = array_map(
            static fn (TwigFunction $function): array => [$function->getName(), $function->getCallable(), $function->isDeprecated()],
            (new LogLevelExtension())->getFunctions()
        );

        // Prefixed names, and the former ones, deprecated
        self::assertSame([
            ['cleverage_ui_process_log_label', [LogLevelExtensionRuntime::class, 'getLabel'], false],
            ['cleverage_ui_process_log_translation', [LogLevelExtensionRuntime::class, 'getTranslation'], false],
            ['cleverage_ui_process_log_css_class', [LogLevelExtensionRuntime::class, 'getCssClass'], false],
            ['log_label', [LogLevelExtensionRuntime::class, 'getLabel'], true],
            ['log_translation', [LogLevelExtensionRuntime::class, 'getTranslation'], true],
            ['log_css_class', [LogLevelExtensionRuntime::class, 'getCssClass'], true],
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
            foreach ((new LogLevelExtension())->getFunctions() as $function) {
                if ($function->isDeprecated()) {
                    $function->triggerDeprecation();
                }
            }
        } finally {
            restore_error_handler();
        }

        self::assertSame([
            'Since cleverage/ui-process-bundle 3.3: Twig Function "log_label" is deprecated; use "cleverage_ui_process_log_label" instead.',
            'Since cleverage/ui-process-bundle 3.3: Twig Function "log_translation" is deprecated; use "cleverage_ui_process_log_translation" instead.',
            'Since cleverage/ui-process-bundle 3.3: Twig Function "log_css_class" is deprecated; use "cleverage_ui_process_log_css_class" instead.',
        ], $deprecations);
    }
}
