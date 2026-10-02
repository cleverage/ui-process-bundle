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
            static fn (TwigFunction $function): array => [$function->getName(), $function->getCallable()],
            (new LogLevelExtension())->getFunctions()
        );

        self::assertSame([
            ['log_label', [LogLevelExtensionRuntime::class, 'getLabel']],
            ['log_translation', [LogLevelExtensionRuntime::class, 'getTranslation']],
            ['log_css_class', [LogLevelExtensionRuntime::class, 'getCssClass']],
        ], $functions);
    }
}
