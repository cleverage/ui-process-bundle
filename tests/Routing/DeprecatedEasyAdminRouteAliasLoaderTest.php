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

namespace CleverAge\UiProcessBundle\Tests\Routing;

use CleverAge\UiProcessBundle\Controller\Admin\ProcessDashboardController;
use CleverAge\UiProcessBundle\Routing\DeprecatedEasyAdminRouteAliasLoader;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Loader\Loader;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

#[CoversClass(DeprecatedEasyAdminRouteAliasLoader::class)]
class DeprecatedEasyAdminRouteAliasLoaderTest extends TestCase
{
    public function testAliasesOfTheRoutesOfTheDashboard(): void
    {
        $easyAdminRoutes = new RouteCollection();
        $easyAdminRoutes->add('cleverage_ui_process', $this->createRoute('/process', ProcessDashboardController::class));
        $easyAdminRoutes->add('cleverage_ui_process_process_execution_index', $this->createRoute('/process/process-execution', ProcessDashboardController::class));
        // Routes of another dashboard (of the application)
        $easyAdminRoutes->add('admin', $this->createRoute('/admin', 'App\Controller\Admin\DashboardController'));
        $easyAdminRoutes->add('cleverage_ui_process_other', $this->createRoute('/admin/other', 'App\Controller\Admin\DashboardController'));
        // Not generated from the dashboard route name
        $easyAdminRoutes->add('cleverage_ui_processes', $this->createRoute('/processes', ProcessDashboardController::class));

        $loader = new DeprecatedEasyAdminRouteAliasLoader();
        $loader->setResolver(new LoaderResolver([$this->createEasyAdminLoader($easyAdminRoutes)]));
        self::assertTrue($loader->supports('.', DeprecatedEasyAdminRouteAliasLoader::TYPE));
        self::assertFalse($loader->supports('.', 'easyadmin.routes'));

        $aliases = $loader->load('.', DeprecatedEasyAdminRouteAliasLoader::TYPE);

        self::assertSame([], $aliases->all());
        self::assertSame(['process', 'process_process_execution_index'], array_keys($aliases->getAliases()));
        $alias = $aliases->getAlias('process_process_execution_index');
        self::assertNotNull($alias);
        self::assertSame('cleverage_ui_process_process_execution_index', $alias->getId());
        self::assertSame(
            [
                'package' => 'cleverage/ui-process-bundle',
                'version' => '3.3',
                'message' => 'The "process_process_execution_index" route alias is deprecated, use the "cleverage_ui_process_process_execution_index" route instead. It will be removed in 4.0.',
            ],
            $alias->getDeprecation('process_process_execution_index')
        );
        self::assertSame('cleverage_ui_process', $aliases->getAlias('process')?->getId());
    }

    private function createRoute(string $path, string $dashboardFqcn): Route
    {
        return new Route($path, [EA::DASHBOARD_CONTROLLER_FQCN => $dashboardFqcn]);
    }

    private function createEasyAdminLoader(RouteCollection $routes): Loader
    {
        return new class($routes) extends Loader {
            public function __construct(private readonly RouteCollection $routes)
            {
                parent::__construct();
            }

            public function load(mixed $resource, ?string $type = null): RouteCollection
            {
                return $this->routes;
            }

            public function supports(mixed $resource, ?string $type = null): bool
            {
                return 'easyadmin.routes' === $type;
            }
        };
    }
}
