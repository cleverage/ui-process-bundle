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

use CleverAge\ProcessBundle\Manager\ProcessManagerInterface;
use CleverAge\UiProcessBundle\Manager\UploadedFileManager;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * @internal
 */
#[AsMessageHandler]
readonly class ProcessExecuteHandler
{
    public function __construct(
        private ProcessManagerInterface $manager,
        private ProcessHandler $processHandler,
        private ?UploadedFileManager $uploadedFileManager = null,
    ) {
    }

    public function __invoke(ProcessExecuteMessage $message): void
    {
        $this->processHandler->close();
        try {
            $this->manager->execute($message->code, $message->input, $message->context);
        } finally {
            $this->uploadedFileManager?->deleteInput($message->input);
        }
    }
}
