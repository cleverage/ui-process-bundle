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
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(NotificationTrigger::class)]
#[UsesClass(ProcessExecution::class)]
class NotificationTriggerTest extends TestCase
{
    /**
     * @return iterable<string, array{ProcessExecutionStatus, array<string, mixed>, ?NotificationTrigger}>
     */
    public static function provideProcessExecutions(): iterable
    {
        yield 'started' => [ProcessExecutionStatus::Started, [], null];
        yield 'failed' => [ProcessExecutionStatus::Failed, [], NotificationTrigger::Failed];
        yield 'failed with report' => [ProcessExecutionStatus::Failed, ['Error' => 2], NotificationTrigger::Failed];
        yield 'finish' => [ProcessExecutionStatus::Finish, [], NotificationTrigger::Finish];
        yield 'finish with a custom report key' => [ProcessExecutionStatus::Finish, ['imported' => 12], NotificationTrigger::Finish];
        yield 'finish with report' => [ProcessExecutionStatus::Finish, ['Warning' => 3, 'imported' => 12], NotificationTrigger::FinishWithReport];
    }

    /**
     * @param array<string, mixed> $report
     */
    #[DataProvider('provideProcessExecutions')]
    public function testFromProcessExecution(ProcessExecutionStatus $status, array $report, ?NotificationTrigger $expected): void
    {
        $processExecution = new ProcessExecution('test.process', 'test.log');
        $processExecution->setStatus($status);
        foreach ($report as $key => $value) {
            $processExecution->addReport($key, $value);
        }

        self::assertSame($expected, NotificationTrigger::fromProcessExecution($processExecution));
    }

    public function testGetReportedLevels(): void
    {
        $processExecution = new ProcessExecution('test.process', 'test.log');
        $processExecution->addReport('Warning', 3);
        $processExecution->addReport('imported', 12);
        $processExecution->addReport('Critical', 1);

        self::assertSame(['Warning' => 3, 'Critical' => 1], NotificationTrigger::getReportedLevels($processExecution));
    }

    public function testValues(): void
    {
        self::assertSame(['failed', 'finish_with_report', 'finish'], NotificationTrigger::values());
    }
}
