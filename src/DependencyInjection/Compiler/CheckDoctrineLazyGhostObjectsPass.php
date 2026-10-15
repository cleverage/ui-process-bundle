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

namespace CleverAge\UiProcessBundle\DependencyInjection\Compiler;

use Doctrine\ORM\Proxy\ProxyFactory;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;

/**
 * The entities of the bundle have readonly properties, which the legacy proxies of Doctrine ORM 2 cannot handle
 * ("Cannot unset readonly property ... from scope Proxies\__CG__\..."): fail at compile time with an explicit message
 * when they are used, instead of failing when an entity proxy is loaded.
 *
 * @internal
 */
final readonly class CheckDoctrineLazyGhostObjectsPass implements CompilerPassInterface
{
    public const MESSAGE = 'The entities of the CleverAge UiProcessBundle have readonly properties, which are not supported by the legacy proxies of Doctrine ORM 2: set "doctrine.orm.enable_lazy_ghost_objects" to true (as the Symfony recipe of the DoctrineBundle does), or upgrade to Doctrine ORM 3.';

    /**
     * @param bool|null $legacyProxies whether the installed Doctrine ORM supports the legacy proxies (ORM 2), detected
     *                                 by default
     */
    public function __construct(
        private ?bool $legacyProxies = null,
    ) {
    }

    public function process(ContainerBuilder $container): void
    {
        // ORM 3 always uses lazy ghost (or native lazy) objects; the parameter is set by the DoctrineBundle 2
        if (!($this->legacyProxies ?? method_exists(ProxyFactory::class, 'resetUninitializedProxy'))
            || !$container->hasParameter('doctrine.orm.enable_lazy_ghost_objects')
        ) {
            return;
        }

        if (!$container->getParameter('doctrine.orm.enable_lazy_ghost_objects')) {
            throw new LogicException(self::MESSAGE);
        }
    }
}
