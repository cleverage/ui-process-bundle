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

use CleverAge\ProcessBundle\Manager\ProcessManager;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
readonly class ProcessExecuteHandler
{
    public function __construct(private ProcessManager $manager, private ProcessHandler $processHandler)
    {
    }

    public function __invoke(ProcessExecuteMessage $message): void
    {
        $this->processHandler->close();
        $this->manager->execute($message->code, $message->input, $message->context);
    }
}
