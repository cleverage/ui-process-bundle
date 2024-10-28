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

namespace CleverAge\ProcessUiBundle\Message;

use CleverAge\ProcessBundle\Command\ExecuteProcessCommand;
use Symfony\Component\Console\Exception\ExceptionInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class ProcessRunHandler
{
    public function __construct(private readonly ExecuteProcessCommand $command)
    {
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
