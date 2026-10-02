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

namespace CleverAge\UiProcessBundle\Tests\Twig\Runtime;

use CleverAge\UiProcessBundle\Twig\Runtime\LogLevelExtensionRuntime;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

#[CoversClass(LogLevelExtensionRuntime::class)]
class LogLevelExtensionRuntimeTest extends TestCase
{
    public function testGetLabel(): void
    {
        $runtime = new LogLevelExtensionRuntime();

        self::assertSame('WARNING', $runtime->getLabel(Level::Warning->value));
        self::assertSame('DEBUG', $runtime->getLabel(100));
    }

    public function testGetLabelOfUnknownLevel(): void
    {
        $this->expectException(\ValueError::class);

        (new LogLevelExtensionRuntime())->getLabel(42);
    }

    public function testGetTranslation(): void
    {
        $translator = $this->createMock(TranslatorInterface::class);
        $translator->expects(self::once())
            ->method('trans')
            ->with('enum.log_level.warning', [], 'enums')
            ->willReturn('Avertissement');

        $runtime = new LogLevelExtensionRuntime();
        $runtime->setTranslator($translator);

        self::assertSame('Avertissement', $runtime->getTranslation('WARNING'));
    }

    #[DataProvider('provideCssClass')]
    public function testGetCssClass(Level $level, string $expected): void
    {
        $runtime = new LogLevelExtensionRuntime();

        self::assertSame($expected, $runtime->getCssClass($level->value));
        self::assertSame($expected, $runtime->getCssClass($level->name));
    }

    /**
     * @return iterable<string, array{Level, string}>
     */
    public static function provideCssClass(): iterable
    {
        yield 'debug' => [Level::Debug, 'success'];
        yield 'info' => [Level::Info, 'success'];
        yield 'notice' => [Level::Notice, ''];
        yield 'warning' => [Level::Warning, 'warning'];
        yield 'error' => [Level::Error, 'danger'];
        yield 'critical' => [Level::Critical, 'danger'];
        yield 'alert' => [Level::Alert, 'danger'];
        yield 'emergency' => [Level::Emergency, 'danger'];
    }

    public function testGetCssClassOfUnknownValue(): void
    {
        $runtime = new LogLevelExtensionRuntime();

        self::assertSame('', $runtime->getCssClass(42));
        self::assertSame('', $runtime->getCssClass('WARNING'));
    }
}
