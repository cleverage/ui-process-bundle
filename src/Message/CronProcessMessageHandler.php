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

namespace CleverAge\UiProcessBundle\Message;

use CleverAge\UiProcessBundle\Entity\ProcessSchedule;
use CleverAge\UiProcessBundle\Repository\ProcessScheduleRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CronProcessMessageHandler
{
    public function __construct(
        private MessageBusInterface $bus,
        private ?ProcessScheduleRepository $repository = null,
        private ?LoggerInterface $logger = null,
    ) {
    }

    public function __invoke(CronProcessMessage $message): void
    {
        $schedule = $this->getCurrentSchedule($message->processSchedule);
        if (!$schedule instanceof ProcessSchedule) {
            return;
        }
        /** @var list<array{key: string, value: mixed}> $contextRows */
        $contextRows = $schedule->getContext();
        $context = array_merge(...array_map(static fn (array $ctx) => [$ctx['key'] => $ctx['value']], $contextRows));
        $this->bus->dispatch(
            new ProcessExecuteMessage($schedule->getProcess() ?? '', $schedule->getInput(), $context)
        );
    }

    /**
     * The schedules are loaded when the scheduler worker starts: read the schedule again, so that a schedule disabled
     * or deleted since is not run, and the current input and context are used.
     */
    private function getCurrentSchedule(ProcessSchedule $schedule): ?ProcessSchedule
    {
        $id = $schedule->getId();
        if (!$this->repository instanceof ProcessScheduleRepository || null === $id) {
            return $schedule;
        }

        $current = $this->repository->findFresh($id);
        if (!$current instanceof ProcessSchedule) {
            $this->logger?->info('Process schedule has been deleted, the process is not run.', [
                'process_schedule' => $id,
                'process' => $schedule->getProcess(),
            ]);

            return null;
        }
        if (!$current->isEnabled()) {
            $this->logger?->info('Process schedule is disabled, the process is not run.', [
                'process_schedule' => $id,
                'process' => $current->getProcess(),
            ]);

            return null;
        }

        return $current;
    }
}
