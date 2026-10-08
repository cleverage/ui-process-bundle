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

namespace CleverAge\UiProcessBundle\Tests\Notifier;

use CleverAge\UiProcessBundle\Entity\Enum\ProcessExecutionStatus;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use CleverAge\UiProcessBundle\Notifier\ProcessExecutionNotification;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Notifier\Notification\Notification;
use Symfony\Component\Notifier\Recipient\NoRecipient;

#[CoversClass(ProcessExecutionNotification::class)]
#[UsesClass(NotificationTrigger::class)]
#[UsesClass(ProcessExecution::class)]
class ProcessExecutionNotificationTest extends TestCase
{
    public function testFailed(): void
    {
        $processExecution = $this->createProcessExecution(ProcessExecutionStatus::Failed, ['Error' => 1]);

        $notification = new ProcessExecutionNotification(
            $processExecution,
            NotificationTrigger::Failed,
            new \RuntimeException('Something went wrong'),
            ['chat/slack']
        );

        self::assertSame($processExecution, $notification->processExecution);
        self::assertSame(NotificationTrigger::Failed, $notification->trigger);
        self::assertSame('Process "test.process" failed', $notification->getSubject());
        self::assertSame(Notification::IMPORTANCE_HIGH, $notification->getImportance());
        self::assertSame(['chat/slack'], $notification->getChannels(new NoRecipient()));
        self::assertSame('Something went wrong', $notification->getException()?->getMessage());
        self::assertSame(
            implode("\n", [
                'Status: failed',
                'Start date: '.$processExecution->startDate->format(\DateTimeInterface::ATOM),
                'Duration: 00 hour(s) 00 min(s) 00 s',
                'Report: Error: 1',
                'Error: Something went wrong',
                'Log file: test.process/test.log',
            ]),
            $notification->getContent()
        );
    }

    public function testFinishWithReport(): void
    {
        $processExecution = $this->createProcessExecution(ProcessExecutionStatus::Finish, ['Warning' => 3, 'Error' => 1, 'imported' => 12]);

        $notification = new ProcessExecutionNotification($processExecution, NotificationTrigger::FinishWithReport);

        self::assertSame('Process "test.process" finished with reported logs', $notification->getSubject());
        self::assertSame(Notification::IMPORTANCE_MEDIUM, $notification->getImportance());
        self::assertSame([], $notification->getChannels(new NoRecipient()));
        self::assertNull($notification->getException());
        self::assertStringContainsString("\nReport: Warning: 3, Error: 1\n", $notification->getContent());
        self::assertStringNotContainsString('Error: ', str_replace('Error: 1', '', $notification->getContent()));
    }

    public function testFinish(): void
    {
        $processExecution = $this->createProcessExecution(ProcessExecutionStatus::Finish, []);
        $reflection = new \ReflectionProperty(ProcessExecution::class, 'id');
        $reflection->setValue($processExecution, 42);

        $notification = new ProcessExecutionNotification($processExecution, NotificationTrigger::Finish);

        self::assertSame('Process "test.process" finished', $notification->getSubject());
        self::assertSame(Notification::IMPORTANCE_LOW, $notification->getImportance());
        self::assertStringNotContainsString('Report:', $notification->getContent());
        self::assertStringEndsWith("\nLog file: test.process/test.log\nProcess execution: 42", $notification->getContent());
    }

    public function testRunningProcessExecution(): void
    {
        $processExecution = new ProcessExecution('test.process', 'test.log');

        $notification = new ProcessExecutionNotification($processExecution, NotificationTrigger::Finish);

        self::assertStringContainsString("\nDuration: -\n", $notification->getContent());
    }

    /**
     * @param array<string, mixed> $report
     */
    private function createProcessExecution(ProcessExecutionStatus $status, array $report): ProcessExecution
    {
        $processExecution = new ProcessExecution('test.process', 'test.log');
        $processExecution->setStatus($status);
        foreach ($report as $key => $value) {
            $processExecution->addReport($key, $value);
        }
        $processExecution->end();

        return $processExecution;
    }
}
