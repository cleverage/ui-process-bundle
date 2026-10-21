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
use CleverAge\UiProcessBundle\Controller\Admin\ProcessExecutionCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\UserCrudController;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Repository\ProcessScheduleRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\ParameterBag\EnvPlaceholderParameterBag;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBag;
use Symfony\Component\Yaml\Yaml;

#[CoversClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(NotificationTrigger::class)]
class CleverAgeUiProcessExtensionTest extends TestCase
{
    public function testEveryServiceDefinitionFileIsLoaded(): void
    {
        $container = $this->createContainer('prod');
        (new CleverAgeUiProcessExtension())->load([], $container);

        $files = glob(\dirname(__DIR__, 2).'/config/services/*.yaml');
        self::assertIsArray($files);
        self::assertNotEmpty($files);
        foreach ($files as $file) {
            $config = Yaml::parseFile($file, Yaml::PARSE_CUSTOM_TAGS);
            self::assertIsArray($config);
            if (!isset($config['services'])) {
                continue;
            }
            self::assertIsArray($config['services']);
            foreach (array_keys($config['services']) as $id) {
                if ('_defaults' === $id) {
                    continue;
                }
                if (str_ends_with((string) $id, '\\')) {
                    // Resource: at least one service registered under the namespace prefix
                    $registered = array_filter(
                        array_keys($container->getDefinitions()),
                        static fn (string $definitionId): bool => str_starts_with($definitionId, (string) $id)
                    );
                    self::assertNotEmpty($registered, \sprintf('No service registered for the "%s" resource of %s', $id, basename($file)));
                    continue;
                }
                self::assertTrue($container->has((string) $id), \sprintf('Service "%s" of %s is not registered', $id, basename($file)));
            }
        }
    }

    public function testLoadWithDefaultConfiguration(): void
    {
        $container = $this->createContainer('prod');
        (new CleverAgeUiProcessExtension())->load([], $container);

        // Services of every config/services/*.yaml file are loaded
        self::assertTrue($container->hasDefinition('cleverage_ui_process.manager.process_execution'));
        // Stateful services, reset between two messages of a worker
        self::assertSame([['method' => 'reset']], $container->getDefinition('cleverage_ui_process.manager.process_execution')->getTag('kernel.reset'));
        self::assertSame([['method' => 'reset']], $container->getDefinition('cleverage_ui_process.monolog_handler.doctrine_process')->getTag('kernel.reset'));
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

        $purgeLogsCommand = $container->getDefinition('cleverage_ui_process.command.purge_logs');
        self::assertNull($purgeLogsCommand->getArgument('$databaseMaxDays'));
        self::assertNull($purgeLogsCommand->getArgument('$fileMaxDays'));

        self::assertSame(
            'bundles/cleverageuiprocess/logo.jpg',
            $container->getDefinition(ProcessDashboardController::class)->getArgument('$logoPath')
        );

        $stopChecker = $container->getDefinition('cleverage_ui_process.stop_checker.process_execution');
        self::assertSame(5, $stopChecker->getArgument(2));
        self::assertTrue($stopChecker->hasTag('cleverage_process.stop_checker'));
        self::assertSame(900, $container->getDefinition(ProcessExecutionCrudController::class)->getArgument('$staleAfter'));

        // symfony/notifier is installed (dev dependency)
        self::assertSame(
            ['enabled' => false, 'statuses' => ['failed', 'finish_with_report'], 'channels' => [], 'recipients' => []],
            $container->getDefinition('cleverage_ui_process.notifier.process_execution')->getArgument('$defaultOptions')
        );
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function provideAutowiringAliases(): iterable
    {
        yield 'process execution manager' => [ProcessExecutionManager::class, 'cleverage_ui_process.manager.process_execution'];
        yield 'process configurations manager' => [ProcessConfigurationsManager::class, 'cleverage_ui_process.manager.process_configuration'];
        yield 'process execution repository' => [ProcessExecutionRepository::class, 'cleverage_ui_process.repository.process_execution'];
        yield 'process schedule repository' => [ProcessScheduleRepository::class, 'cleverage_ui_process.repository.process_schedule'];
        yield 'process handler' => [ProcessHandler::class, 'cleverage_ui_process.monolog_handler.process'];
        yield 'doctrine process handler' => [DoctrineProcessHandler::class, 'cleverage_ui_process.monolog_handler.doctrine_process'];
    }

    /**
     * @param class-string $class
     */
    #[DataProvider('provideAutowiringAliases')]
    public function testServiceCanBeAutowired(string $class, string $id): void
    {
        $container = $this->createContainer('prod');
        (new CleverAgeUiProcessExtension())->load([], $container);

        // Private alias named after the class of the service
        $alias = $container->getAlias($class);
        self::assertSame($id, (string) $alias);
        self::assertFalse($alias->isPublic());
        self::assertSame($class, $container->getDefinition($id)->getClass());
    }

    /**
     * @return iterable<string, array{string, mixed}>
     */
    public static function provideInvalidLogLevels(): iterable
    {
        yield 'unknown name' => ['database_level', 'Verbose'];
        yield 'unknown value' => ['file_level', 150];
        yield 'not a level' => ['file_level', true];
        yield 'empty' => ['file_level', ''];
        yield 'report increment level value' => ['report_increment_level', 300];
        yield 'unknown report increment level' => ['report_increment_level', 'Loud'];
    }

    #[DataProvider('provideInvalidLogLevels')]
    public function testInvalidLogLevel(string $option, mixed $level): void
    {
        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage(\sprintf('Invalid configuration for path "clever_age_ui_process.logs.%s": invalid Monolog level %s, use one of: DEBUG, INFO, NOTICE, WARNING, ERROR, CRITICAL, ALERT, EMERGENCY.', $option, json_encode($level)));

        (new CleverAgeUiProcessExtension())->load([['logs' => [$option => $level]]], $this->createContainer('prod'));
    }

    /**
     * @return iterable<string, array{string, int|string}>
     */
    public static function provideValidLogLevels(): iterable
    {
        yield 'name' => ['database_level', 'Error'];
        yield 'lower case name' => ['file_level', 'error'];
        yield 'value' => ['file_level', 400];
        yield 'numeric string' => ['database_level', '400'];
        yield 'report increment level, upper case name' => ['report_increment_level', 'ERROR'];
    }

    #[DataProvider('provideValidLogLevels')]
    public function testValidLogLevel(string $option, int|string $level): void
    {
        $container = $this->createContainer('prod');
        (new CleverAgeUiProcessExtension())->load([['logs' => [$option => $level]]], $container);

        // Passed as is to the handlers
        $fileHandler = $container->getDefinition('cleverage_ui_process.monolog_handler.process');
        $configured = match ($option) {
            'database_level' => $container->getDefinition('cleverage_ui_process.monolog_handler.doctrine_process')->getArgument('$level'),
            'file_level' => $fileHandler->getArgument('$level'),
            default => $fileHandler->getMethodCalls(),
        };
        self::assertSame('report_increment_level' === $option ? [['setReportIncrementLevel', [$level]]] : $level, $configured);
    }

    /**
     * Environment variables are resolved at runtime: not validated when the container is compiled.
     */
    public function testLogLevelFromAnEnvironmentVariable(): void
    {
        $container = $this->createContainer('prod', new EnvPlaceholderParameterBag());
        $placeholder = $container->getParameterBag()->get('env(UI_LOG_LEVEL)');
        self::assertIsString($placeholder);

        (new CleverAgeUiProcessExtension())->load([['logs' => ['file_level' => $placeholder, 'report_increment_level' => $placeholder]]], $container);

        self::assertSame($placeholder, $container->getDefinition('cleverage_ui_process.monolog_handler.process')->getArgument('$level'));
    }

    public function testGetConfigurationUsesTheKernelEnvironment(): void
    {
        $configuration = (new CleverAgeUiProcessExtension())->getConfiguration([], $this->createContainer('dev'));

        /** @var array{logs: array{file_level: string}} $config */
        $config = (new Processor())->processConfiguration($configuration, []);
        self::assertSame('Debug', $config['logs']['file_level']);
    }

    public function testLoadWithNotificationConfiguration(): void
    {
        $container = $this->createContainer('prod');
        (new CleverAgeUiProcessExtension())->load([
            ['notification' => ['enabled' => true, 'statuses' => ['finish'], 'channels' => ['chat/slack']]],
        ], $container);

        self::assertSame(
            ['enabled' => true, 'statuses' => ['finish'], 'channels' => ['chat/slack'], 'recipients' => []],
            $container->getDefinition('cleverage_ui_process.notifier.process_execution')->getArgument('$defaultOptions')
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
                    'database_max_days' => 30,
                    'file_max_days' => 180,
                ],
                'design' => ['logo_path' => 'images/my-logo.png'],
                'process_execution' => ['heartbeat_interval' => 10, 'stale_after' => 3600],
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
        self::assertSame(['disable', []], array_last($calls));

        $purgeLogsCommand = $container->getDefinition('cleverage_ui_process.command.purge_logs');
        self::assertSame(30, $purgeLogsCommand->getArgument('$databaseMaxDays'));
        self::assertSame(180, $purgeLogsCommand->getArgument('$fileMaxDays'));

        self::assertSame(
            'images/my-logo.png',
            $container->getDefinition(ProcessDashboardController::class)->getArgument('$logoPath')
        );

        self::assertSame(10, $container->getDefinition('cleverage_ui_process.stop_checker.process_execution')->getArgument(2));
        self::assertSame(3600, $container->getDefinition(ProcessExecutionCrudController::class)->getArgument('$staleAfter'));
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
                    'form_login' => ['login_path' => 'cleverage_ui_process_login', 'check_path' => 'cleverage_ui_process_login'],
                    'logout' => ['path' => 'cleverage_ui_process_logout', 'target' => 'cleverage_ui_process_login', 'clear_site_data' => '*'],
                ],
            ],
            $security[0]['firewalls']
        );
    }

    public function testPrependWithoutFirewallConfiguration(): void
    {
        $container = $this->createContainer('prod');
        foreach (['monolog', 'doctrine_migrations', 'security'] as $alias) {
            $container->registerExtension($this->createExtension($alias));
        }
        $container->loadFromExtension('security', ['firewalls' => ['main' => ['provider' => 'app_users']]]);
        $container->registerExtension(new CleverAgeUiProcessExtension());
        $container->loadFromExtension('clever_age_ui_process', ['security' => ['configure_firewall' => true]]);
        $container->loadFromExtension('clever_age_ui_process', ['security' => ['configure_firewall' => false]]);

        (new CleverAgeUiProcessExtension())->prepend($container);

        // Only the application configuration: the last "configure_firewall" value wins
        self::assertSame(
            [['firewalls' => ['main' => ['provider' => 'app_users']]]],
            $container->getExtensionConfig('security')
        );
        self::assertCount(1, $container->getExtensionConfig('monolog'));
        self::assertCount(1, $container->getExtensionConfig('doctrine_migrations'));
    }

    public function testPrependWithAParameterAsFirewallConfiguration(): void
    {
        $container = $this->createContainer('prod');
        $container->registerExtension($this->createExtension('security'));
        $container->registerExtension(new CleverAgeUiProcessExtension());
        $container->loadFromExtension('clever_age_ui_process', ['security' => ['configure_firewall' => '%env(bool:UI_FIREWALL)%']]);

        $this->expectException(InvalidConfigurationException::class);
        $this->expectExceptionMessage('The "clever_age_ui_process.security.configure_firewall" option must be a boolean, "string" given');

        (new CleverAgeUiProcessExtension())->prepend($container);
    }

    public function testPrependWithoutTheConfiguredBundles(): void
    {
        $container = $this->createContainer('prod');

        (new CleverAgeUiProcessExtension())->prepend($container);

        self::assertSame([], $container->getExtensionConfig('monolog'));
        self::assertSame([], $container->getExtensionConfig('doctrine_migrations'));
        self::assertSame([], $container->getExtensionConfig('security'));
        self::assertCount(1, $container->getExtensionConfig('framework'));
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

    private function createContainer(string $env, ParameterBag $parameterBag = new ParameterBag()): ContainerBuilder
    {
        $parameterBag->add([
            'kernel.environment' => $env,
            'kernel.debug' => false,
            'kernel.project_dir' => sys_get_temp_dir(),
            'kernel.logs_dir' => sys_get_temp_dir().'/logs',
            'kernel.cache_dir' => sys_get_temp_dir().'/cache',
        ]);

        return new ContainerBuilder($parameterBag);
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
