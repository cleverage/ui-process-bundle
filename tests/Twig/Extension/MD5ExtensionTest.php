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

use CleverAge\UiProcessBundle\Twig\Extension\MD5Extension;
use CleverAge\UiProcessBundle\Twig\Runtime\MD5ExtensionRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Twig\TwigFilter;

#[CoversClass(MD5Extension::class)]
class MD5ExtensionTest extends TestCase
{
    public function testGetFilters(): void
    {
        $filters = array_map(
            static fn (TwigFilter $filter): array => [$filter->getName(), $filter->getCallable(), $filter->isDeprecated()],
            (new MD5Extension())->getFilters()
        );

        // Prefixed names, and the former ones, deprecated
        self::assertSame([
            ['cleverage_ui_process_md5', [MD5ExtensionRuntime::class, 'md5'], false],
            ['md5', [MD5ExtensionRuntime::class, 'md5'], true],
        ], $filters);
    }

    public function testFormerNamesAreDeprecated(): void
    {
        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            foreach ((new MD5Extension())->getFilters() as $filter) {
                if ($filter->isDeprecated()) {
                    $filter->triggerDeprecation();
                }
            }
        } finally {
            restore_error_handler();
        }

        self::assertSame([
            'Since cleverage/ui-process-bundle 3.3: Twig Filter "md5" is deprecated; use "cleverage_ui_process_md5" instead.',
        ], $deprecations);
    }
}
