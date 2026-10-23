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

namespace CleverAge\UiProcessBundle\Tests\EventSubscriber;

use CleverAge\UiProcessBundle\EventSubscriber\UploadedFileSubscriber;
use CleverAge\UiProcessBundle\Manager\UploadedFileManager;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ServiceLocator;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\Event\WorkerMessageHandledEvent;
use Symfony\Component\Messenger\Event\WorkerMessageReceivedEvent;
use Symfony\Component\Messenger\EventListener\SendFailedMessageToFailureTransportListener;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

#[CoversClass(UploadedFileSubscriber::class)]
#[UsesClass(UploadedFileManager::class)]
#[UsesClass(ProcessExecuteMessage::class)]
class UploadedFileSubscriberTest extends TestCase
{
    /**
     * Failure transports by transport name, with a global failure transport ("failed").
     */
    private const FAILURE_TRANSPORTS = ['execute_process' => 'failed', 'failed' => 'failed'];

    /**
     * The failure transport has a failure transport of its own.
     */
    private const CHAINED_FAILURE_TRANSPORTS = ['execute_process' => 'failed', 'failed' => 'failed2'];

    private string $directory;
    private string $input;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir().\DIRECTORY_SEPARATOR.uniqid('uploaded_file_subscriber_test_', true);
        $this->input = $this->directory.'/input.csv';
        (new Filesystem())->dumpFile($this->input, 'data');
    }

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->directory);
    }

    public function testSubscribedEvents(): void
    {
        self::assertSame(
            [
                WorkerMessageReceivedEvent::class => 'onMessageReceived',
                WorkerMessageHandledEvent::class => 'onMessageHandled',
                WorkerMessageFailedEvent::class => 'onMessageFailed',
            ],
            UploadedFileSubscriber::getSubscribedEvents()
        );
    }

    public function testMessageHandledByWorker(): void
    {
        $subscriber = new UploadedFileSubscriber(new UploadedFileManager($this->directory));
        $message = new ProcessExecuteMessage('test.process', $this->input, [], true);
        $envelope = new Envelope($message);
        self::assertFalse($subscriber->isHandledByWorker($message));

        $subscriber->onMessageReceived(new WorkerMessageReceivedEvent($envelope, 'execute_process'));
        self::assertTrue($subscriber->isHandledByWorker($message));
        self::assertFalse($subscriber->isHandledByWorker(new ProcessExecuteMessage('test.process', $this->input, [], true)));

        $subscriber->onMessageHandled(new WorkerMessageHandledEvent($envelope, 'execute_process'));
        self::assertFalse($subscriber->isHandledByWorker($message));
        // Deleted by the handler after a successful execution
        self::assertFileExists($this->input);

        $subscriber->onMessageReceived(new WorkerMessageReceivedEvent($envelope, 'execute_process'));
        $subscriber->onMessageFailed(new WorkerMessageFailedEvent($envelope, 'execute_process', new \RuntimeException()));
        self::assertFalse($subscriber->isHandledByWorker($message));
    }

    /**
     * @param array<string, string>|null $failureTransportsByName
     */
    #[DataProvider('provideFailedMessage')]
    public function testFailedMessage(bool $deleteInput, bool $willRetry, string $receiverName, ?string $sentToFailureTransportFrom, ?array $failureTransportsByName, bool $deleted): void
    {
        $subscriber = new UploadedFileSubscriber(
            new UploadedFileManager($this->directory),
            $this->getFailureSenders($failureTransportsByName ?? self::FAILURE_TRANSPORTS),
            $failureTransportsByName,
        );

        $subscriber->onMessageFailed($this->createFailedEvent($deleteInput, $willRetry, $receiverName, $sentToFailureTransportFrom));

        self::assertSame(!$deleted, file_exists($this->input));
    }

    /**
     * @return iterable<string, array{bool, bool, string, string|null, array<string, string>|null, bool}>
     */
    public static function provideFailedMessage(): iterable
    {
        yield 'not retried, without failure transport' => [true, false, 'other', null, self::FAILURE_TRANSPORTS, true];
        yield 'retried' => [true, true, 'execute_process', null, self::FAILURE_TRANSPORTS, false];
        // Kept for messenger:failed:retry
        yield 'sent to the failure transport' => [true, false, 'execute_process', null, self::FAILURE_TRANSPORTS, false];
        yield 'sent to the failure transport, Symfony < 6.4.34' => [true, false, 'execute_process', null, null, false];
        // Failing again when retried from the failure transport: dropped by Symfony
        yield 'received from the failure transport' => [true, false, 'failed', 'execute_process', self::FAILURE_TRANSPORTS, true];
        yield 'received from the failure transport, Symfony < 6.4.34' => [true, false, 'failed', 'execute_process', null, true];
        yield 'received from the failure transport, retried' => [true, true, 'failed', 'execute_process', self::FAILURE_TRANSPORTS, false];
        // The failure transport has a failure transport of its own
        yield 'received from the failure transport, sent to another one' => [true, false, 'failed', 'execute_process', self::CHAINED_FAILURE_TRANSPORTS, false];
        // Opted out of the failure transport
        yield 'already sent to the failure transport of its transport' => [true, false, 'execute_process', 'execute_process', self::FAILURE_TRANSPORTS, true];
        yield 'given by its path' => [false, false, 'other', null, self::FAILURE_TRANSPORTS, false];
        yield 'given by its path, sent to the failure transport' => [false, false, 'execute_process', null, self::FAILURE_TRANSPORTS, false];
    }

    /**
     * The file is kept exactly when the Symfony listener sends the message to a failure transport (installed version).
     *
     * @param array<string, string>|null $failureTransportsByName
     */
    #[DataProvider('provideFailedMessage')]
    public function testFailedMessageAsTheSymfonyFailureTransportListener(bool $deleteInput, bool $willRetry, string $receiverName, ?string $sentToFailureTransportFrom, ?array $failureTransportsByName, bool $deleted): void
    {
        $version = InstalledVersions::getVersion('symfony/messenger') ?? '';
        if (version_compare($version, '6.4.5', '<')) {
            self::markTestSkipped('Before Symfony 6.4.5, a message already sent to a failure transport is sent again.');
        }
        // As the UploadedFileSubscriberPass: the failure transport names only when the listener has this argument
        $listenerHasFailureTransportsByName = (new \ReflectionMethod(SendFailedMessageToFailureTransportListener::class, '__construct'))->getNumberOfParameters() > 2;
        if (!$listenerHasFailureTransportsByName && null !== $failureTransportsByName) {
            $failureTransportsByName = null;
        }
        $failureTransports = $failureTransportsByName ?? self::FAILURE_TRANSPORTS;
        $transports = [];
        foreach (array_unique(array_values($failureTransports)) as $name) {
            $transports[$name] = new InMemoryTransport();
        }
        $failureSenders = new ServiceLocator(array_map(
            static fn (string $failureTransport): \Closure => static fn (): InMemoryTransport => $transports[$failureTransport],
            $failureTransports
        ));
        $eventDispatcher = new EventDispatcher();
        $eventDispatcher->addSubscriber(new UploadedFileSubscriber(new UploadedFileManager($this->directory), $failureSenders, $failureTransportsByName));
        $eventDispatcher->addSubscriber(new SendFailedMessageToFailureTransportListener($failureSenders, null, $failureTransports));

        $eventDispatcher->dispatch($this->createFailedEvent($deleteInput, $willRetry, $receiverName, $sentToFailureTransportFrom));

        $sent = array_sum(array_map(static fn (InMemoryTransport $transport): int => \count($transport->getSent()), $transports));
        self::assertSame($deleteInput && !$willRetry && 0 === $sent, !file_exists($this->input));
        if ($listenerHasFailureTransportsByName) {
            self::assertSame($deleted, !file_exists($this->input));
        }
    }

    /**
     * @param array<string, string> $failureTransportsByName
     *
     * @return ServiceLocator<mixed>
     */
    private function getFailureSenders(array $failureTransportsByName): ServiceLocator
    {
        return new ServiceLocator(array_map(
            static fn (): \Closure => static fn (): InMemoryTransport => new InMemoryTransport(),
            $failureTransportsByName
        ));
    }

    private function createFailedEvent(bool $deleteInput, bool $willRetry, string $receiverName, ?string $sentToFailureTransportFrom): WorkerMessageFailedEvent
    {
        $envelope = new Envelope(new ProcessExecuteMessage('test.process', $this->input, [], $deleteInput));
        if (null !== $sentToFailureTransportFrom) {
            $envelope = $envelope->with(new SentToFailureTransportStamp($sentToFailureTransportFrom));
        }
        $event = new WorkerMessageFailedEvent($envelope, $receiverName, new \RuntimeException('Process failed'));
        if ($willRetry) {
            $event->setForRetry();
        }

        return $event;
    }

    public function testWithoutFailureTransports(): void
    {
        $subscriber = new UploadedFileSubscriber(new UploadedFileManager($this->directory));

        $subscriber->onMessageFailed(new WorkerMessageFailedEvent(
            new Envelope(new ProcessExecuteMessage('test.process', $this->input, [], true)),
            'execute_process',
            new \RuntimeException('Process failed')
        ));

        self::assertFileDoesNotExist($this->input);
    }

    /**
     * A message queued by a previous version of the bundle, without the "deleteInput" property: its input is kept.
     */
    public function testMessageOfAPreviousVersion(): void
    {
        $subscriber = new UploadedFileSubscriber(new UploadedFileManager($this->directory));
        $message = unserialize(\sprintf(
            'O:%d:"%s":3:{s:4:"code";s:12:"test.process";s:5:"input";s:%d:"%s";s:7:"context";a:0:{}}',
            \strlen(ProcessExecuteMessage::class),
            ProcessExecuteMessage::class,
            \strlen($this->input),
            $this->input
        ));
        self::assertInstanceOf(ProcessExecuteMessage::class, $message);

        $subscriber->onMessageFailed(new WorkerMessageFailedEvent(new Envelope($message), 'execute_process', new \RuntimeException('Failed')));

        self::assertFileExists($this->input);
    }

    public function testOtherMessagesAreIgnored(): void
    {
        $subscriber = new UploadedFileSubscriber(new UploadedFileManager($this->directory));

        $subscriber->onMessageFailed(new WorkerMessageFailedEvent(
            new Envelope(new \stdClass()),
            'execute_process',
            new \RuntimeException('Failed')
        ));

        self::assertFileExists($this->input);
    }
}
