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
use CleverAge\ProcessBundle\Registry\TaskRegistry;
use CleverAge\ProcessBundle\Task\DummyTask;
use CleverAge\ProcessBundle\Task\File\FileReaderTask;
use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use Doctrine\Bundle\MigrationsBundle\DoctrineMigrationsBundle;
use Doctrine\ORM\Proxy\ProxyFactory;
use EasyCorp\Bundle\EasyAdminBundle\EasyAdminBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\MonologBundle\MonologBundle;
use Symfony\Bundle\SecurityBundle\SecurityBundle;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Yaml\Yaml;
use Symfony\UX\TwigComponent\TwigComponentBundle;
use Twig\Extra\TwigExtraBundle\TwigExtraBundle;

/**
 * Application used by the functional tests: the bundle with its dependencies, a SQLite database and in-memory
 * Messenger transports, in a temporary directory of its own (per PHP process).
 */
class TestKernel extends Kernel
{
    use MicroKernelTrait;

    /**
     * Service of the tasks used by config/processes.yaml, by code.
     */
    private const TASK_SERVICES = [
        'dummy' => DummyTask::class,
        'file_reader' => FileReaderTask::class,
    ];

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
        $container->extension('clever_age_process', $this->getProcessConfigurations());
        if (Kernel::VERSION_ID >= 70300) {
            // Not setting it is deprecated since Symfony 7.3: its default value changes in 8.0
            $container->extension('framework', ['property_info' => ['with_constructor_extractor' => true]]);
        } else {
            // Symfony 6.4 calls User::eraseCredentials(), marked #[\Deprecated] for Symfony 7.3 (deprecation since PHP 8.4)
            $container->extension('security', ['erase_credentials' => false]);
        }
        // Doctrine ORM 2 (lowest dependencies): lazy ghost proxies, as configured by the Symfony recipe, the legacy
        // proxies cannot unset the readonly properties of the entities (ProcessExecution::$code...), see
        // CheckDoctrineLazyGhostObjectsPass
        if (method_exists(ProxyFactory::class, 'resetUninitializedProxy')) {
            $container->extension('doctrine', ['orm' => ['enable_lazy_ghost_objects' => true]]);
        }
    }

    protected function configureRoutes(\Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator $routes): void
    {
        $routes->import(__DIR__.'/config/routes.yaml');
    }

    /**
     * The processes of config/processes.yaml, whose tasks are referenced by code (task: <code>): by their service with
     * cleverage/process-bundle < 5.4.
     *
     * @return array<string, mixed>
     */
    private function getProcessConfigurations(): array
    {
        /** @var array{configurations: array<string, array{tasks: array<string, array<string, mixed>>}>} $configurations */
        // PARSE_CONSTANT: enum cases given with the "!php/enum" tag
        $configurations = Yaml::parseFile(__DIR__.'/config/processes.yaml', Yaml::PARSE_CONSTANT);
        if (class_exists(TaskRegistry::class)) {
            return $configurations;
        }
        foreach ($configurations['configurations'] as $processCode => $process) {
            foreach ($process['tasks'] as $taskCode => $task) {
                \assert(\is_string($task['task']) && isset(self::TASK_SERVICES[$task['task']]));
                $task['service'] = '@'.self::TASK_SERVICES[$task['task']];
                unset($task['task']);
                $configurations['configurations'][$processCode]['tasks'][$taskCode] = $task;
            }
        }

        return $configurations;
    }

    public static function getRunDir(): string
    {
        return self::$runDir ??= sys_get_temp_dir().'/ui_process_bundle_tests_'.getmypid();
    }
}
