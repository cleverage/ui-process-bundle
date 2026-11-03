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
use CleverAge\UiProcessBundle\EventSubscriber\UploadedFileSubscriber;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Manager\UploadedFileManager;
use CleverAge\UiProcessBundle\Message\ProcessExecuteHandler;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;

#[CoversClass(ProcessExecuteHandler::class)]
#[UsesClass(ProcessExecuteMessage::class)]
#[UsesClass(ProcessHandler::class)]
#[UsesClass(UploadedFileManager::class)]
#[UsesClass(UploadedFileSubscriber::class)]
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

    /**
     * The input is deleted only when the message is flagged (file written by the launch form or the HTTP API), after a
     * successful or a failed execution outside of a worker; never a file of the upload directory given by its path.
     */
    #[DataProvider('provideDeleteInput')]
    public function testDeleteInput(bool $deleteInput, bool $fails, bool $handledByWorker, bool $deleted): void
    {
        $directory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('process_execute_handler_test_', true);
        $input = $directory.'/input.csv';
        (new Filesystem())->dumpFile($input, 'data');
        $message = new ProcessExecuteMessage('test.process', $input, [], $deleteInput);

        $manager = $this->createStub(ProcessManagerInterface::class);
        if ($fails) {
            $manager->method('execute')->willThrowException(new \RuntimeException('Process failed'));
        }
        $uploadedFileManager = new UploadedFileManager($directory);
        $subscriber = new UploadedFileSubscriber($uploadedFileManager);
        if ($handledByWorker) {
            $subscriber->onMessageReceived(new WorkerMessageReceivedEvent(new Envelope($message), 'execute_process'));
        }
        $handler = new ProcessExecuteHandler(
            $manager,
            new ProcessHandler(sys_get_temp_dir(), $this->createStub(ProcessExecutionManager::class)),
            $uploadedFileManager,
            $subscriber,
        );

        try {
            $handler($message);
            self::assertFalse($fails, 'The process should have failed');
        } catch (\RuntimeException $e) {
            self::assertSame('Process failed', $e->getMessage());
        } finally {
            $exists = file_exists($input);
            (new Filesystem())->remove($directory);
        }

        self::assertSame(!$deleted, $exists);
    }

    /**
     * @return iterable<string, array{bool, bool, bool, bool}>
     */
    public static function provideDeleteInput(): iterable
    {
        yield 'uploaded, success' => [true, false, false, true];
        yield 'uploaded, failure' => [true, true, false, true];
        yield 'uploaded, success in a worker' => [true, false, true, true];
        // The UploadedFileSubscriber deletes it unless the message will be retried
        yield 'uploaded, failure in a worker' => [true, true, true, false];
        yield 'given by its path, success' => [false, false, false, false];
        yield 'given by its path, failure' => [false, true, false, false];
        yield 'given by its path, success in a worker' => [false, false, true, false];
    }

    /**
     * A message queued by a previous version of the bundle, without the "deleteInput" property: its input is kept.
     */
    public function testMessageOfAPreviousVersion(): void
    {
        $directory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('process_execute_handler_test_', true);
        $input = $directory.'/input.csv';
        (new Filesystem())->dumpFile($input, 'data');
        $message = unserialize(\sprintf(
            'O:%d:"%s":3:{s:4:"code";s:12:"test.process";s:5:"input";s:%d:"%s";s:7:"context";a:0:{}}',
            \strlen(ProcessExecuteMessage::class),
            ProcessExecuteMessage::class,
            \strlen($input),
            $input
        ));
        self::assertInstanceOf(ProcessExecuteMessage::class, $message);
        $handler = new ProcessExecuteHandler(
            $this->createStub(ProcessManagerInterface::class),
            new ProcessHandler(sys_get_temp_dir(), $this->createStub(ProcessExecutionManager::class)),
            new UploadedFileManager($directory),
        );

        $handler($message);

        $exists = file_exists($input);
        (new Filesystem())->remove($directory);
        self::assertTrue($exists);
    }

    public function testWithoutUploadedFileSubscriberTheUploadedInputIsDeletedWhenTheProcessFails(): void
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
            $handler(new ProcessExecuteMessage('test.process', $input, [], true));
            self::fail('The process should have failed');
        } catch (\RuntimeException) {
        } finally {
            $exists = file_exists($input);
            (new Filesystem())->remove($directory);
        }

        self::assertFalse($exists);
    }
}
