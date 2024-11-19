<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Message;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class CronProcessMessageHandler
{
    public function __construct(private MessageBusInterface $bus)
    {
    }

    public function __invoke(CronProcessMessage $message): void
    {
        $schedule = $message->processSchedule;
        $context = array_merge(...array_map(function($ctx) {
            return [$ctx['key'] => $ctx['value']];
        }, $schedule->getContext()));
        $this->bus->dispatch(
            new ProcessExecuteMessage($schedule->getProcess(), $schedule->getInput(), $context)
        );
    }
}
