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

namespace CleverAge\UiProcessBundle\Tests\Controller;

use CleverAge\UiProcessBundle\Controller\Admin\Security\LoginController;
use CleverAge\UiProcessBundle\Controller\Admin\Security\LogoutController;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Every controller of the bundle must be protected by #[IsGranted]: with EasyAdmin 5, each CRUD controller has its
 * own routes, the #[IsGranted] of the dashboard controller does not apply to them (GHSA-r3m7-69c2-2vmp).
 */
#[CoversNothing]
class AccessControlTest extends TestCase
{
    /** Controllers that must be reachable without authentication */
    private const PUBLIC_CONTROLLERS = [LoginController::class, LogoutController::class];

    /**
     * @return iterable<string, array{class-string}>
     */
    public static function provideControllers(): iterable
    {
        $finder = (new Finder())->files()->in(\dirname(__DIR__, 2).'/src/Controller')->name('*.php');
        foreach ($finder as $file) {
            /** @var class-string $class */
            $class = 'CleverAge\\UiProcessBundle\\Controller\\'.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());
            if (!\in_array($class, self::PUBLIC_CONTROLLERS, true) && !(new \ReflectionClass($class))->isAbstract()) {
                yield $class => [$class];
            }
        }
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideControllers')]
    public function testControllerIsProtected(string $class): void
    {
        $attributes = (new \ReflectionClass($class))->getAttributes(IsGranted::class);

        self::assertNotEmpty($attributes, "{$class} must be protected by #[IsGranted]");
    }
}
