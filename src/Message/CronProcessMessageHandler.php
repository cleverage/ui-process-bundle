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

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
readonly final class CronProcessMessageHandler
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function __invoke(CronProcessMessage $message): void
    {
        $schedule = $message->processSchedule;
        $context = array_merge(...array_map(fn ($ctx) => [$ctx['key'] => $ctx['value']], $schedule->getContext()));
        $this->bus->dispatch(
            new ProcessExecuteMessage($schedule->getProcess() ?? '', $schedule->getInput(), $context)
        );
    }
}
