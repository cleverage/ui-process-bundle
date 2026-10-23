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

use CleverAge\UiProcessBundle\DependencyInjection\Compiler\LoginThrottlingPass;
use CleverAge\UiProcessBundle\Security\HttpProcessExecutionAuthenticator;
use CleverAge\UiProcessBundle\Security\HttpProcessExecutionRequestRateLimiter;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpFoundation\RateLimiter\RequestRateLimiterInterface;

#[CoversClass(LoginThrottlingPass::class)]
class LoginThrottlingPassTest extends TestCase
{
    private const AUTHENTICATOR = 'cleverage_ui_process.security.http_process_execution_authenticator';

    public function testTheLimiterOfEveryFirewallIsDecorated(): void
    {
        $container = $this->createContainer();
        $this->registerFirewall($container, 'main', 'security.login_throttling.main.limiter');
        // A firewall of the application with a custom limiter (login_throttling.limiter)
        $this->registerFirewall($container, 'api', 'app.limiter', defaultLimiter: false);

        (new LoginThrottlingPass())->process($container);

        foreach (['main' => 'security.login_throttling.main.limiter', 'api' => 'app.limiter'] as $firewall => $limiter) {
            $listener = $container->getDefinition('security.listener.login_throttling.'.$firewall);
            $decoratorId = 'cleverage_ui_process.security.login_throttling.'.$firewall.'.limiter';
            self::assertEquals(new Reference($decoratorId), $listener->getArgument(1));

            $decorator = $container->getDefinition($decoratorId);
            self::assertSame(RequestRateLimiterInterface::class, $decorator->getClass());
            self::assertSame([HttpProcessExecutionRequestRateLimiter::class, 'create'], $decorator->getFactory());
            self::assertEquals([new Reference($limiter), new Reference(self::AUTHENTICATOR)], $decorator->getArguments());
        }
        // The parent definition of the listeners is left alone
        self::assertSame([], $container->getDefinition('security.listener.login_throttling')->getArguments());
    }

    public function testWithoutTheAuthenticator(): void
    {
        $container = $this->createContainer();
        $container->removeDefinition(self::AUTHENTICATOR);
        $this->registerFirewall($container, 'main', 'security.login_throttling.main.limiter');

        (new LoginThrottlingPass())->process($container);

        self::assertEquals(
            new Reference('security.login_throttling.main.limiter'),
            $container->getDefinition('security.listener.login_throttling.main')->getArgument(1)
        );
    }

    public function testWithoutTheRateLimiter(): void
    {
        $container = $this->createContainer();
        $container->removeDefinition('limiter');
        $this->registerFirewall($container, 'main', 'security.login_throttling.main.limiter');

        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('The login throttling of the "main" firewall requires the rate limiter, disabled by the "framework.rate_limiter" option: enable it, or disable the login throttling of the firewall ("login_throttling: false").');

        (new LoginThrottlingPass())->process($container);
    }

    /**
     * A custom limiter (login_throttling.limiter) does not require the rate limiter of the framework.
     */
    public function testCustomLimiterWithoutTheRateLimiter(): void
    {
        $container = $this->createContainer();
        $container->removeDefinition('limiter');
        $this->registerFirewall($container, 'main', 'app.limiter', defaultLimiter: false);

        (new LoginThrottlingPass())->process($container);

        self::assertEquals(
            new Reference('cleverage_ui_process.security.login_throttling.main.limiter'),
            $container->getDefinition('security.listener.login_throttling.main')->getArgument(1)
        );
    }

    private function createContainer(): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->register(self::AUTHENTICATOR, HttpProcessExecutionAuthenticator::class);
        $container->register('limiter');
        $container->register('security.listener.login_throttling')->setAbstract(true);

        return $container;
    }

    /**
     * Services registered by the LoginThrottlingFactory of Symfony.
     */
    private function registerFirewall(ContainerBuilder $container, string $firewall, string $limiter, bool $defaultLimiter = true): void
    {
        if ($defaultLimiter) {
            $container->setDefinition('limiter._login_local_'.$firewall, new ChildDefinition('limiter'));
            $container->setDefinition('limiter._login_global_'.$firewall, new ChildDefinition('limiter'));
            $container->setDefinition($limiter, new Definition());
        }
        $container->setDefinition('security.listener.login_throttling.'.$firewall, new ChildDefinition('security.listener.login_throttling'))
            ->replaceArgument(1, new Reference($limiter));
    }
}
