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

namespace CleverAge\UiProcessBundle\Tests\DependencyInjection;

use CleverAge\UiProcessBundle\Controller\Admin\ProcessDashboardController;
use CleverAge\UiProcessBundle\Controller\Admin\UserCrudController;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;

#[CoversClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(Configuration::class)]
class CleverAgeUiProcessExtensionTest extends TestCase
{
    public function testLoadWithDefaultConfiguration(): void
    {
        $container = $this->createContainer('prod');
        (new CleverAgeUiProcessExtension())->load([], $container);

        // Services of every config/services/*.yaml file are loaded
        self::assertTrue($container->hasDefinition('cleverage_ui_process.manager.process_execution'));
        self::assertTrue($container->hasParameter('upload_directory'));

        self::assertSame(
            ['ROLE_ADMIN' => 'ROLE_ADMIN'],
            $container->getDefinition(UserCrudController::class)->getArgument('$roles')
        );

        $fileHandler = $container->getDefinition('cleverage_ui_process.monolog_handler.process');
        self::assertSame('Info', $fileHandler->getArgument('$level'));
        self::assertSame([['setReportIncrementLevel', ['Warning']]], $fileHandler->getMethodCalls());

        $databaseHandler = $container->getDefinition('cleverage_ui_process.monolog_handler.doctrine_process');
        self::assertSame('Info', $databaseHandler->getArgument('$level'));
        self::assertFalse($databaseHandler->hasMethodCall('disable'));

        self::assertSame(
            'bundles/cleverageuiprocess/logo.jpg',
            $container->getDefinition(ProcessDashboardController::class)->getArgument('$logoPath')
        );
    }

    public function testLoadWithDefaultConfigurationInDevEnvironment(): void
    {
        $container = $this->createContainer('dev');
        (new CleverAgeUiProcessExtension())->load([], $container);

        self::assertSame(
            'Debug',
            $container->getDefinition('cleverage_ui_process.monolog_handler.process')->getArgument('$level')
        );
        self::assertSame(
            'Debug',
            $container->getDefinition('cleverage_ui_process.monolog_handler.doctrine_process')->getArgument('$level')
        );
    }

    public function testLoadWithCustomConfiguration(): void
    {
        $container = $this->createContainer('prod');
        (new CleverAgeUiProcessExtension())->load([
            [
                'security' => ['roles' => ['ROLE_ADMIN', 'ROLE_OPERATOR']],
                'logs' => [
                    'store_in_database' => false,
                    'database_level' => 'Error',
                    'file_level' => 'Notice',
                    'report_increment_level' => 'Critical',
                ],
                'design' => ['logo_path' => 'images/my-logo.png'],
            ],
        ], $container);

        self::assertSame(
            ['ROLE_ADMIN' => 'ROLE_ADMIN', 'ROLE_OPERATOR' => 'ROLE_OPERATOR'],
            $container->getDefinition(UserCrudController::class)->getArgument('$roles')
        );

        $fileHandler = $container->getDefinition('cleverage_ui_process.monolog_handler.process');
        self::assertSame('Notice', $fileHandler->getArgument('$level'));
        self::assertSame([['setReportIncrementLevel', ['Critical']]], $fileHandler->getMethodCalls());

        $databaseHandler = $container->getDefinition('cleverage_ui_process.monolog_handler.doctrine_process');
        self::assertSame('Error', $databaseHandler->getArgument('$level'));
        self::assertTrue($databaseHandler->hasMethodCall('disable'));
        $calls = $databaseHandler->getMethodCalls();
        self::assertSame(['disable', []], end($calls));

        self::assertSame(
            'images/my-logo.png',
            $container->getDefinition(ProcessDashboardController::class)->getArgument('$logoPath')
        );
    }

    public function testPrepend(): void
    {
        $container = $this->createContainer('prod');
        foreach (['monolog', 'doctrine_migrations', 'security'] as $alias) {
            $container->registerExtension($this->createExtension($alias));
        }

        (new CleverAgeUiProcessExtension())->prepend($container);

        $monolog = $container->getExtensionConfig('monolog');
        self::assertCount(1, $monolog);
        self::assertSame(
            [
                'pb_ui_file' => ['type' => 'service', 'id' => 'cleverage_ui_process.monolog_handler.process'],
                'pb_ui_orm' => ['type' => 'service', 'id' => 'cleverage_ui_process.monolog_handler.doctrine_process'],
                'pb_ui_file_filter' => [
                    'type' => 'filter',
                    'handler' => 'pb_ui_file',
                    'channels' => ['cleverage_process', 'cleverage_process_task'],
                ],
                'pb_ui_orm_filter' => [
                    'type' => 'filter',
                    'handler' => 'pb_ui_orm',
                    'channels' => ['cleverage_process', 'cleverage_process_task'],
                ],
            ],
            $monolog[0]['handlers']
        );

        $migrations = $container->getExtensionConfig('doctrine_migrations');
        self::assertCount(1, $migrations);
        $paths = $migrations[0]['migrations_paths'];
        self::assertIsArray($paths);
        self::assertSame(['CleverAge\UiProcessBundle\Migrations'], array_keys($paths));
        self::assertIsString($paths['CleverAge\UiProcessBundle\Migrations']);
        self::assertSame(
            realpath(__DIR__.'/../../src/Migrations'),
            realpath($paths['CleverAge\UiProcessBundle\Migrations'])
        );

        self::assertSame(
            [
                [
                    'messenger' => [
                        'transport' => [
                            [
                                'name' => 'execute_process',
                                'dsn' => 'doctrine://default',
                                'retry_strategy' => ['max_retries' => 0],
                            ],
                        ],
                        'routing' => [ProcessExecuteMessage::class => 'execute_process'],
                    ],
                ],
            ],
            $container->getExtensionConfig('framework')
        );

        $security = $container->getExtensionConfig('security');
        self::assertCount(1, $security);
        self::assertSame(
            ['process_user_provider' => ['entity' => ['class' => User::class, 'property' => 'email']]],
            $security[0]['providers']
        );
        self::assertSame(
            [
                'main' => [
                    'provider' => 'process_user_provider',
                    'custom_authenticator' => ['cleverage_ui_process.security.http_process_execution_authenticator'],
                    'form_login' => ['login_path' => 'process_login', 'check_path' => 'process_login', 'enable_csrf' => true],
                    'logout' => ['path' => 'process_logout', 'target' => 'process_login', 'clear_site_data' => '*'],
                ],
            ],
            $security[0]['firewalls']
        );
    }

    public function testPrependedFrameworkConfigurationComesFirst(): void
    {
        $container = $this->createContainer('prod');
        foreach (['monolog', 'doctrine_migrations', 'security', 'framework'] as $alias) {
            $container->registerExtension($this->createExtension($alias));
        }
        $container->loadFromExtension('framework', ['secret' => 'app']);

        (new CleverAgeUiProcessExtension())->prepend($container);

        // The application configuration is processed last, so it can override the bundle one
        $framework = $container->getExtensionConfig('framework');
        self::assertCount(2, $framework);
        self::assertArrayHasKey('messenger', $framework[0]);
        self::assertSame(['secret' => 'app'], $framework[1]);
    }

    private function createContainer(string $env): ContainerBuilder
    {
        return new ContainerBuilder(new ParameterBag([
            'kernel.environment' => $env,
            'kernel.debug' => false,
            'kernel.project_dir' => sys_get_temp_dir(),
            'kernel.logs_dir' => sys_get_temp_dir().'/logs',
            'kernel.cache_dir' => sys_get_temp_dir().'/cache',
        ]));
    }

    private function createExtension(string $alias): Extension
    {
        return new class($alias) extends Extension {
            public function __construct(private readonly string $alias)
            {
            }

            public function load(array $configs, ContainerBuilder $container): void
            {
            }

            public function getAlias(): string
            {
                return $this->alias;
            }
        };
    }
}
