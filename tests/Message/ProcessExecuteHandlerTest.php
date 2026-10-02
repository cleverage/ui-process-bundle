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

namespace CleverAge\UiProcessBundle\Tests\Message;

use CleverAge\ProcessBundle\Manager\ProcessManager;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Message\ProcessExecuteHandler;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessExecuteHandler::class)]
#[UsesClass(ProcessExecuteMessage::class)]
#[UsesClass(ProcessHandler::class)]
class ProcessExecuteHandlerTest extends TestCase
{
    public function testExecuteTheProcessWithANewLogFile(): void
    {
        $processHandler = new ProcessHandler(sys_get_temp_dir(), $this->createStub(ProcessExecutionManager::class));
        // Log file of a previous execution, in the same worker
        $processHandler->setFilename('previous.log');

        $manager = $this->createMock(ProcessManager::class);
        $manager->expects(self::once())
            ->method('execute')
            ->with('test.process', 'data.csv', ['foo' => 'bar'])
            ->willReturnCallback(static function () use ($processHandler): mixed {
                // The log file of the previous execution is closed before the new execution
                self::assertFalse($processHandler->hasFilename());

                return null;
            });

        (new ProcessExecuteHandler($manager, $processHandler))(
            new ProcessExecuteMessage('test.process', 'data.csv', ['foo' => 'bar'])
        );
    }
}
