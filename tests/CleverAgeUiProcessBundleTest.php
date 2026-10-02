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

namespace CleverAge\UiProcessBundle\Tests;

use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
class CleverAgeUiProcessBundleTest extends TestCase
{
    public function testPathIsTheBundleRootDirectory(): void
    {
        $bundle = new CleverAgeUiProcessBundle();

        self::assertSame(\dirname(__DIR__), $bundle->getPath());
        self::assertDirectoryExists($bundle->getPath().'/config/services');
        self::assertDirectoryExists($bundle->getPath().'/templates');
    }

    public function testContainerExtension(): void
    {
        $extension = (new CleverAgeUiProcessBundle())->getContainerExtension();

        self::assertInstanceOf(CleverAgeUiProcessExtension::class, $extension);
        self::assertSame('clever_age_ui_process', $extension->getAlias());
    }
}
