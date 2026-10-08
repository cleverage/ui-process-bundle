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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Processes of the test application executed with the notifier enabled (null chatter transport).
 */
#[CoversClass(ProcessExecutionNotifier::class)]
#[CoversClass(ProcessExecutionEndedEvent::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessExecution::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(ProcessExecutionManager::class)]
#[UsesClass(DoctrineProcessHandler::class)]
#[UsesClass(ProcessHandler::class)]
#[UsesClass(ProcessExecutionRepository::class)]
#[UsesClass(NotificationTrigger::class)]
#[UsesClass(ProcessExecutionNotification::class)]
class ProcessExecutionNotificationTest extends FunctionalTestCase
{
    public function testNotificationEnabledByTheProcess(): void
    {
        $this->getProcessManager()->execute('test.notification');

        self::assertNotificationCount(1);
        self::assertStringContainsString('Process "test.notification" finished', (string) self::getNotifierMessage()?->getSubject());
        self::assertSame('test', self::getNotifierMessage()?->getTransport());
    }

    public function testFailedProcessNotified(): void
    {
        try {
            $this->getProcessManager()->execute('test.notification_failing');
            self::fail('The process should have failed');
        } catch (\Throwable) {
        }

        self::assertNotificationCount(1);
        self::assertStringContainsString('Process "test.notification_failing" failed', (string) self::getNotifierMessage()?->getSubject());
    }

    public function testNotificationDisabledByDefault(): void
    {
        $this->getProcessManager()->execute('test.process');
        try {
            $this->getProcessManager()->execute('test.failing');
        } catch (\Throwable) {
        }

        self::assertNotificationCount(0);
    }

    private function getProcessManager(): ProcessManager
    {
        /** @var ProcessManager $processManager */
        $processManager = static::getContainer()->get('cleverage_process.manager.process');

        return $processManager;
    }
}
