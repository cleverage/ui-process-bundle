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
            static fn (TwigFilter $filter): array => [$filter->getName(), $filter->getCallable()],
            (new MD5Extension())->getFilters()
        );

        self::assertSame([['md5', [MD5ExtensionRuntime::class, 'md5']]], $filters);
    }
}
