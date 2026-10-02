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

namespace CleverAge\UiProcessBundle\Tests\Controller\Admin\Security;

use CleverAge\UiProcessBundle\Controller\Admin\Security\LogoutController;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * The firewall logout listener handles /process/logout before the controller (see the security configuration
 * prepended by the bundle): the controller is only a fallback.
 */
#[CoversClass(LogoutController::class)]
class LogoutControllerTest extends TestCase
{
    public function testLogout(): void
    {
        $security = $this->createMock(Security::class);
        $security->expects(self::once())->method('logout');
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route, array $parameters, int $type): string => UrlGeneratorInterface::ABSOLUTE_PATH === $type && 'process_login' === $route ? '/process/login' : '');
        $container = new Container();
        $container->set('router', $router);

        $controller = new LogoutController($security);
        $controller->setContainer($container);
        $response = $controller();

        self::assertInstanceOf(RedirectResponse::class, $response);
        self::assertSame('/process/login', $response->getTargetUrl());
    }
}
