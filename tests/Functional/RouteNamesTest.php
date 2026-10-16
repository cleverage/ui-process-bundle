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

namespace CleverAge\UiProcessBundle\Tests\Functional;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Router;

/**
 * The routes of the bundle are prefixed with "cleverage_ui_process_" (config/routes/), their former names are
 * deprecated aliases (config/routes/deprecated_aliases.yaml).
 */
#[CoversNothing]
class RouteNamesTest extends FunctionalTestCase
{
    /**
     * @return iterable<string, array{string, string, string, 3?: string}>
     */
    public static function provideRoutes(): iterable
    {
        yield 'login' => ['cleverage_ui_process_login', 'process_login', '/process/login'];
        yield 'logout' => ['cleverage_ui_process_logout', 'process_logout', '/process/logout'];
        yield 'process list' => ['cleverage_ui_process_list', 'process_list', '/process/list'];
        yield 'process launch' => ['cleverage_ui_process_launch', 'process_launch', '/process/launch'];
        yield 'HTTP API' => ['cleverage_ui_process_http_execute', 'http_process_execute', '/http/process/execute', 'POST'];
    }

    #[DataProvider('provideRoutes')]
    public function testRoute(string $name, string $formerName, string $path, string $method = 'GET'): void
    {
        $router = static::getContainer()->get('router');
        self::assertInstanceOf(Router::class, $router);

        self::assertSame($path, $router->generate($name));
        self::assertSame($name, $router->matchRequest(Request::create($path, $method))['_route'] ?? null);

        $deprecations = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$deprecations): bool {
            $deprecations[] = $errstr;

            return true;
        }, \E_USER_DEPRECATED);
        try {
            self::assertSame($path, $router->generate($formerName));
        } finally {
            restore_error_handler();
        }
        self::assertContains(
            \sprintf('Since cleverage/ui-process-bundle 3.3: The "%s" route alias is deprecated, use the "%s" route instead. It will be removed in 4.0.', $formerName, $name),
            $deprecations
        );
    }
}
