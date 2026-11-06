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

use CleverAge\UiProcessBundle\CacheWarmer\LaunchFormsCacheWarmer;
use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckDoctrineLazyGhostObjectsPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\LoginThrottlingPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\MonologFilterLevelPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadDirectoryParameterPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadedFileSubscriberPass;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Event\ProcessExecutionEndedEvent;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use CleverAge\UiProcessBundle\Notifier\ProcessExecutionNotification;
use CleverAge\UiProcessBundle\Notifier\ProcessExecutionNotifier;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Routing\DeprecatedEasyAdminRouteAliasLoader;
use CleverAge\UiProcessBundle\StopChecker\ProcessExecutionStopChecker;
use Monolog\Handler\FilterHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * The "filter" handlers of the bundle accept the level of the handler they wrap: the processors of the logger do not run
 * for the records none of them writes.
 */
#[CoversClass(MonologFilterLevelPass::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(ProcessExecutionRepository::class)]
#[UsesClass(LaunchFormsCacheWarmer::class)]
#[UsesClass(ProcessExecutionNotifier::class)]
#[UsesClass(ProcessExecutionEndedEvent::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CheckDoctrineLazyGhostObjectsPass::class)]
#[UsesClass(UploadDirectoryParameterPass::class)]
#[UsesClass(LoginThrottlingPass::class)]
#[UsesClass(UploadedFileSubscriberPass::class)]
#[UsesClass(DeprecatedEasyAdminRouteAliasLoader::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessExecution::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(ProcessExecutionManager::class)]
#[UsesClass(DoctrineProcessHandler::class)]
#[UsesClass(ProcessHandler::class)]
#[UsesClass(NotificationTrigger::class)]
#[UsesClass(ProcessExecutionNotification::class)]
#[UsesClass(ProcessExecutionStopChecker::class)]
class MonologFilterLevelTest extends FunctionalTestCase
{
    public function testTheFiltersAcceptTheLevelOfTheirHandler(): void
    {
        self::assertSame(['pb_ui_file' => Level::Info, 'pb_ui_orm' => Level::Info], $this->getFilterMinimumLevels());

        $this->createTestClient(['clever_age_ui_process' => ['logs' => ['file_level' => 'Notice', 'database_level' => 'error']]]);
        self::assertSame(['pb_ui_file' => Level::Notice, 'pb_ui_orm' => Level::Error], $this->getFilterMinimumLevels());
    }

    /**
     * @return array<string, Level> minimum level of the filter of each handler of the bundle
     */
    private function getFilterMinimumLevels(): array
    {
        /** @var Logger $logger */
        $logger = static::getContainer()->get('monolog.logger.cleverage_process');
        $levels = [];
        foreach ($logger->getHandlers() as $handler) {
            if (!$handler instanceof FilterHandler) {
                continue;
            }
            $wrapped = $handler->getHandler();
            $name = $wrapped instanceof DoctrineProcessHandler ? 'pb_ui_orm' : ($wrapped instanceof ProcessHandler ? 'pb_ui_file' : null);
            if (null !== $name) {
                $levels[$name] = $handler->getAcceptedLevels()[0];
            }
        }
        ksort($levels);

        return $levels;
    }
}
