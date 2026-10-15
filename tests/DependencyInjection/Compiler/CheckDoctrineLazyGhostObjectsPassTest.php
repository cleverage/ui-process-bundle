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

namespace CleverAge\UiProcessBundle\Tests\DependencyInjection\Compiler;

use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckDoctrineLazyGhostObjectsPass;
use Doctrine\ORM\Proxy\ProxyFactory;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

#[CoversClass(CheckDoctrineLazyGhostObjectsPass::class)]
class CheckDoctrineLazyGhostObjectsPassTest extends TestCase
{
    public function testLegacyProxiesWithoutLazyGhostObjectsFail(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.orm.enable_lazy_ghost_objects', false);

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage(CheckDoctrineLazyGhostObjectsPass::MESSAGE);

        (new CheckDoctrineLazyGhostObjectsPass(legacyProxies: true))->process($container);
    }

    /**
     * @return iterable<string, array{bool, bool|null}>
     */
    public static function provideValidConfigurations(): iterable
    {
        yield 'ORM 2 with lazy ghost objects' => [true, true];
        yield 'ORM 2 without the DoctrineBundle parameter' => [true, null];
        yield 'ORM 3' => [false, false];
        yield 'ORM 3 without the DoctrineBundle parameter' => [false, null];
    }

    #[DataProvider('provideValidConfigurations')]
    #[DoesNotPerformAssertions]
    public function testValidConfigurations(bool $legacyProxies, ?bool $lazyGhostObjects): void
    {
        $container = new ContainerBuilder();
        if (null !== $lazyGhostObjects) {
            $container->setParameter('doctrine.orm.enable_lazy_ghost_objects', $lazyGhostObjects);
        }

        (new CheckDoctrineLazyGhostObjectsPass($legacyProxies))->process($container);
    }

    public function testInstalledOrmIsDetectedByDefault(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('doctrine.orm.enable_lazy_ghost_objects', false);

        // ORM 3 (no legacy proxy): nothing to check; ORM 2 (lowest dependencies): the check fails
        if (method_exists(ProxyFactory::class, 'resetUninitializedProxy')) {
            $this->expectException(LogicException::class);
        } else {
            $this->addToAssertionCount(1);
        }

        (new CheckDoctrineLazyGhostObjectsPass())->process($container);
    }
}
