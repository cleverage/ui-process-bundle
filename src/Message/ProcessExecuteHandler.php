<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Message;

use CleverAge\ProcessBundle\Manager\ProcessManager;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ProcessExecuteHandler
{
    public function __construct(private ProcessManager $manager)
    {
    }

    public function __invoke(ProcessExecuteMessage $message): void
    {
        $this->manager->execute($message->code, $message->input, $message->context);
    }
}
