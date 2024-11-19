<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Scheduler;

use CleverAge\ProcessUiBundle\Entity\ProcessScheduleType;
use CleverAge\ProcessUiBundle\Message\CronProcessMessage;
use CleverAge\ProcessUiBundle\Repository\ProcessScheduleRepository;
use Monolog\Attribute\WithMonologChannel;
use Psr\Log\LoggerInterface;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsSchedule('cron')]
#[WithMonologChannel('scheduler')]
readonly class CronScheduler implements ScheduleProviderInterface
{
    public function __construct(
        private ProcessScheduleRepository $repository,
        private ValidatorInterface $validator,
        private LoggerInterface $logger
    ) {
    }

    public function getSchedule(): Schedule
    {
        $schedule = new Schedule();
        try {
            foreach ($this->repository->findAll() as $processSchedule) {
                $violations = $this->validator->validate($processSchedule);
                if (0 !== $violations->count()) {
                    foreach ($violations as $violation) {
                        $this->logger->info(
                            'Scheduler configuration is not valid.',
                            ['reason' => $violation->getMessage()]
                        );
                    }
                    continue;
                }
                if (ProcessScheduleType::CRON === $processSchedule->getType()) {
                    $schedule->add(
                        RecurringMessage::cron(
                            $processSchedule->getExpression(),
                            new CronProcessMessage($processSchedule)
                        )
                    );
                } elseif (ProcessScheduleType::EVERY === $processSchedule->getType()) {
                    $schedule->add(
                        RecurringMessage::every(
                            $processSchedule->getExpression(),
                            new CronProcessMessage($processSchedule)
                        )
                    );
                }
            }
        } catch (\Exception $e) {
            $this->logger->critical($e->getMessage());
        }

        return $schedule;
    }
}
