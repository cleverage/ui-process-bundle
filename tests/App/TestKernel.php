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

namespace CleverAge\UiProcessBundle\Tests\App;

use CleverAge\ProcessBundle\CleverAgeProcessBundle;
use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

/**
 * Application used by the functional tests: the bundle with its dependencies, a SQLite database and in-memory
 * Messenger transports, in a temporary directory of its own (per PHP process).
 */
class TestKernel extends Kernel
{
    use MicroKernelTrait;

    private static ?string $runDir = null;

    public function registerBundles(): iterable
    {
        yield new FrameworkBundle();
        yield new SecurityBundle();
        yield new TwigBundle();
        yield new TwigExtraBundle();
        yield new MonologBundle();
        yield new DoctrineBundle();
        yield new DoctrineMigrationsBundle();
        yield new EasyAdminBundle();
        yield new TwigComponentBundle();
        yield new CleverAgeProcessBundle();
        yield new CleverAgeUiProcessBundle();
    }

    public function getProjectDir(): string
    {
        return __DIR__;
    }

    public function getCacheDir(): string
    {
        return self::getRunDir().'/cache/'.$this->environment;
    }

    public function getLogDir(): string
    {
        return self::getRunDir().'/logs';
    }

    protected function configureContainer(\Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator $container): void
    {
        $container->import(__DIR__.'/config/packages.yaml');
    }

    protected function configureRoutes(\Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/config/routes.yaml');
    }

    public static function getRunDir(): string
    {
        return self::$runDir ??= sys_get_temp_dir().'/ui_process_bundle_tests_'.getmypid();
    }
}
