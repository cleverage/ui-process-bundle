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

namespace CleverAge\UiProcessBundle\Notifier;

use CleverAge\UiProcessBundle\Entity\Enum\ProcessExecutionStatus;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use Monolog\Level;

/**
 * The end of a process execution which can trigger a notification.
 */
enum NotificationTrigger: string
{
    case Failed = 'failed';
    /**
     * Finished with log levels counted in its report (see the "logs.report_increment_level" configuration), or stopped
     * on purpose by a StopTask with such log levels.
     */
    case FinishWithReport = 'finish_with_report';
    case Finish = 'finish';
    /** Stopped on a stop request from the UI, or on purpose by a StopTask without log levels counted in its report */
    case Stopped = 'stopped';

    public static function fromProcessExecution(ProcessExecution $processExecution): ?self
    {
        return match ($processExecution->status) {
            ProcessExecutionStatus::Failed => self::Failed,
            ProcessExecutionStatus::Finish => [] === self::getReportedLevels($processExecution) ? self::Finish : self::FinishWithReport,
            // A stop request ends with a process failure, counted in the report: only the StopTask reports matter
            ProcessExecutionStatus::Stopped => $processExecution->isStopRequested() || [] === self::getReportedLevels($processExecution) ? self::Stopped : self::FinishWithReport,
            ProcessExecutionStatus::Started => null,
        };
    }

    /**
     * The log levels counted in the report of the process execution, other report keys are left out.
     *
     * @return array<string, mixed>
     */
    public static function getReportedLevels(ProcessExecution $processExecution): array
    {
        /** @var array<string, mixed> $report */
        $report = $processExecution->getReport();

        return array_intersect_key($report, array_flip(array_map(static fn (Level $level): string => $level->name, Level::cases())));
    }

    /**
     * @return string[]
     */
    public static function values(): array
    {
        return array_map(static fn (self $trigger): string => $trigger->value, self::cases());
    }
}
