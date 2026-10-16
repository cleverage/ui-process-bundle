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
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckDoctrineLazyGhostObjectsPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckLaunchFormsPass;
use CleverAge\UiProcessBundle\Routing\DeprecatedEasyAdminRouteAliasLoader;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;

#[CoversClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(DeprecatedEasyAdminRouteAliasLoader::class)]
#[UsesClass(CheckDoctrineLazyGhostObjectsPass::class)]
#[UsesClass(CheckLaunchFormsPass::class)]
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

    public function testDoctrineLazyGhostObjectsCheckIsRegistered(): void
    {
        $container = new ContainerBuilder();
        (new CleverAgeUiProcessBundle())->build($container);

        $passes = array_filter(
            $container->getCompilerPassConfig()->getPasses(),
            static fn (object $pass): bool => $pass instanceof CheckDoctrineLazyGhostObjectsPass,
        );
        self::assertCount(1, $passes);
    }

    public function testLaunchFormsCheckIsRegistered(): void
    {
        $container = new ContainerBuilder();
        (new CleverAgeUiProcessBundle())->build($container);

        $passes = array_filter(
            $container->getCompilerPassConfig()->getPasses(),
            static fn (object $pass): bool => $pass instanceof CheckLaunchFormsPass,
        );
        self::assertCount(1, $passes);
    }
}
