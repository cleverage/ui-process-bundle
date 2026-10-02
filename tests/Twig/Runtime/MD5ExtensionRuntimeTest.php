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

use CleverAge\UiProcessBundle\Twig\Runtime\MD5ExtensionRuntime;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MD5ExtensionRuntime::class)]
class MD5ExtensionRuntimeTest extends TestCase
{
    public function testMd5(): void
    {
        $runtime = new MD5ExtensionRuntime();

        self::assertSame('d41d8cd98f00b204e9800998ecf8427e', $runtime->md5(''));
        self::assertSame('5d41402abc4b2a76b9719d911017c592', $runtime->md5('hello'));
    }
}
