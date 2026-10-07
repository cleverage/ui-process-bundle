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

namespace CleverAge\UiProcessBundle\Scheduler;

use CleverAge\UiProcessBundle\Entity\Enum\ProcessScheduleType;
use CleverAge\UiProcessBundle\Entity\ProcessSchedule;
use CleverAge\UiProcessBundle\Message\CronProcessMessage;
use CleverAge\UiProcessBundle\Repository\ProcessScheduleRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

readonly class CronScheduler implements ScheduleProviderInterface
{
    public function __construct(
        private ProcessScheduleRepository $repository,
        private ValidatorInterface $validator,
        private LoggerInterface $logger,
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();
        try {
            $processSchedules = $this->repository->findAll();
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());

            return $schedule;
        }

        // Each process schedule in its own try: an error on one of them must not skip the next ones
        foreach ($processSchedules as $processSchedule) {
            try {
                $this->add($schedule, $processSchedule);
            } catch (\Throwable $e) {
                $this->logger->critical($e->getMessage(), [
                    'process_schedule' => $processSchedule->getId(),
                    'process' => $processSchedule->getProcess(),
                    'expression' => $processSchedule->getExpression(),
                ]);
            }
        }

        return $schedule;
    }

    private function add(Schedule $schedule, ProcessSchedule $processSchedule): void
    {
        $violations = $this->validator->validate($processSchedule);
        if (0 !== $violations->count()) {
            foreach ($violations as $violation) {
                $this->logger->info(
                    'Scheduler configuration is not valid.',
                    ['reason' => $violation->getMessage()]
                );
            }

            return;
        }
        if (ProcessScheduleType::CRON === $processSchedule->getType()) {
            $schedule->add(
                RecurringMessage::cron(
                    $processSchedule->getExpression() ?? '',
                    new CronProcessMessage($processSchedule)
                )
            );
        } elseif (ProcessScheduleType::EVERY === $processSchedule->getType()) {
            $schedule->add(
                RecurringMessage::every(
                    $processSchedule->getExpression() ?? '',
                    new CronProcessMessage($processSchedule)
                )
            );
        }
    }
}
