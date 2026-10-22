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

use CleverAge\ProcessBundle\Manager\ProcessManagerInterface;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Manager\UploadedFileManager;
use CleverAge\UiProcessBundle\Message\ProcessExecuteHandler;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;

#[CoversClass(ProcessExecuteHandler::class)]
#[UsesClass(ProcessExecuteMessage::class)]
#[UsesClass(ProcessHandler::class)]
#[UsesClass(UploadedFileManager::class)]
class ProcessExecuteHandlerTest extends TestCase
{
    public function testExecuteTheProcessWithANewLogFile(): void
    {
        $processHandler = new ProcessHandler(sys_get_temp_dir(), $this->createStub(ProcessExecutionManager::class));
        // Log file of a previous execution, in the same worker
        $processHandler->setFilename('previous.log');

        $manager = $this->createMock(ProcessManagerInterface::class);
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

    public function testTheUploadedInputIsDeletedEvenWhenTheProcessFails(): void
    {
        $directory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('process_execute_handler_test_', true);
        $input = $directory.'/input.csv';
        (new Filesystem())->dumpFile($input, 'data');

        $manager = $this->createStub(ProcessManagerInterface::class);
        $manager->method('execute')->willThrowException(new \RuntimeException('Process failed'));
        $handler = new ProcessExecuteHandler(
            $manager,
            new ProcessHandler(sys_get_temp_dir(), $this->createStub(ProcessExecutionManager::class)),
            new UploadedFileManager($directory),
        );

        try {
            $handler(new ProcessExecuteMessage('test.process', $input));
            self::fail('The process should have failed');
        } catch (\RuntimeException) {
        } finally {
            $exists = file_exists($input);
            (new Filesystem())->remove($directory);
        }

        self::assertFalse($exists);
    }
}
