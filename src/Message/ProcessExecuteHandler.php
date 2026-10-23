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
use CleverAge\UiProcessBundle\EventSubscriber\UploadedFileSubscriber;
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
        private ?UploadedFileSubscriber $uploadedFileSubscriber = null,
    ) {
    }

    public function __invoke(ProcessExecuteMessage $message): void
    {
        $this->processHandler->close();
        try {
            $this->manager->execute($message->code, $message->input, $message->context);
        } catch (\Throwable $e) {
            // In a worker, the message may be retried: the UploadedFileSubscriber deletes the input when it will not
            if (!$this->uploadedFileSubscriber?->isHandledByWorker($message)) {
                $this->deleteInput($message);
            }

            throw $e;
        }
        $this->deleteInput($message);
    }

    private function deleteInput(ProcessExecuteMessage $message): void
    {
        // Uninitialized in a message queued by a previous version of the bundle
        if ($message->deleteInput ?? false) { // @phpstan-ignore nullCoalesce.property
            $this->uploadedFileManager?->deleteInput($message->input);
        }
    }
}
