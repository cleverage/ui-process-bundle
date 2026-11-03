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

use CleverAge\ProcessBundle\Manager\ProcessManager;
use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckDoctrineLazyGhostObjectsPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\LoginThrottlingPass;
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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * The process executions, their logs and their heartbeat written with the entity manager given by its name
 * (process_execution.entity_manager): the default one of the test application, by its name.
 */
#[CoversClass(CleverAgeUiProcessExtension::class)]
#[CoversClass(ProcessExecutionRepository::class)]
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
class ProcessExecutionEntityManagerTest extends FunctionalTestCase
{
    public function testProcessExecutionWrittenWithTheEntityManager(): void
    {
        $this->createTestClient(['clever_age_ui_process' => ['process_execution' => ['entity_manager' => 'default']]]);
        /** @var ProcessManager $processManager */
        $processManager = static::getContainer()->get('cleverage_process.manager.process');
        $processManager->execute('test.process');

        self::assertSame(
            ['finish'],
            $this->getEntityManager()->getConnection()->fetchFirstColumn('SELECT status FROM process_execution')
        );
        /** @var ProcessExecutionRepository $repository */
        $repository = static::getContainer()->get('cleverage_ui_process.repository.process_execution');
        $managerName = new \ReflectionProperty(ProcessExecutionRepository::class, 'managerName');
        self::assertSame('default', $managerName->getValue($repository));
    }
}
