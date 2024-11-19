<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Message;

use CleverAge\ProcessBundle\Manager\ProcessManager;
use CleverAge\ProcessUiBundle\Monolog\Handler\ProcessHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ProcessExecuteHandler
{
    public function __construct(private ProcessManager $manager, private readonly ProcessHandler $processHandler)
    {
    }

    public function __invoke(ProcessExecuteMessage $message): void
    {
        $this->processHandler->close();
        $this->manager->execute($message->code, $message->input, $message->context);
    }
}
