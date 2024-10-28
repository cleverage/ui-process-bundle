<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Message;

use CleverAge\ProcessBundle\Command\ExecuteProcessCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessRunHandler
{
    private ExecuteProcessCommand $command;

    public function __construct(ExecuteProcessCommand $command)
    {
        $this->command = $command;
    }

    /**
     * @throws ExceptionInterface
     */
    public function __invoke(ProcessRunMessage $processRunMessage): void
    {
        $this->command->run(
            new ArrayInput(
                [
                    'processCodes' => [$processRunMessage->getProcessCode()],
                ]
            ),
            new NullOutput()
        );
    }
}
