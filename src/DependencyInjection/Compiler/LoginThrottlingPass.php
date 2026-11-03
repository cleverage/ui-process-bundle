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

use CleverAge\UiProcessBundle\Security\HttpProcessExecutionRequestRateLimiter;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;

/**
 * The login throttling of a firewall (Symfony "login_throttling", added to the "main" firewall by the bundle) does not
 * apply to the requests of the HTTP API: the limiter of its listener is decorated by
 * HttpProcessExecutionRequestRateLimiter, the firewall settings (max_attempts, interval, limiter...) still apply to the
 * other requests.
 *
 * Without the rate limiter of the framework ("framework.rate_limiter: false"), the default limiter of the login
 * throttling cannot be created: explain how to fix it, instead of the "Parent definition "limiter" does not exist" error.
 *
 * @internal
 */
final readonly class LoginThrottlingPass implements CompilerPassInterface
{
    /** Listener of the login throttling of a firewall, see Symfony LoginThrottlingFactory */
    private const LISTENER_PREFIX = 'security.listener.login_throttling.';
    private const AUTHENTICATOR = 'cleverage_ui_process.security.http_process_execution_authenticator';

    public function process(ContainerBuilder $container): void
    {
        foreach ($container->getDefinitions() as $id => $definition) {
            if (!str_starts_with($id, self::LISTENER_PREFIX)) {
                continue;
            }
            $firewall = substr($id, \strlen(self::LISTENER_PREFIX));

            if ($container->hasDefinition('limiter._login_local_'.$firewall) && !$container->hasDefinition('limiter')) {
                throw new LogicException(\sprintf('The login throttling of the "%s" firewall requires the rate limiter, disabled by the "framework.rate_limiter" option: enable it, or disable the login throttling of the firewall ("login_throttling: false").', $firewall));
            }

            if (!$container->hasDefinition(self::AUTHENTICATOR)) {
                continue;
            }
            $limiter = $definition->getArgument(1);
            $container->register($limiterId = 'cleverage_ui_process.security.login_throttling.'.$firewall.'.limiter', RequestRateLimiterInterface::class)
                ->setFactory([HttpProcessExecutionRequestRateLimiter::class, 'create'])
                ->setArguments([$limiter, new Reference(self::AUTHENTICATOR)]);
            $definition->replaceArgument(1, new Reference($limiterId));
        }
    }
}
