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

namespace CleverAge\UiProcessBundle\Tests\Functional;

use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\Controller\Admin\LogRecordCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessDashboardController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessExecutionCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\ProcessScheduleCrudController;
use CleverAge\UiProcessBundle\Controller\Admin\UserCrudController;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckDoctrineLazyGhostObjectsPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\LoginThrottlingPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadDirectoryParameterPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadedFileSubscriberPass;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\Event\ProcessExecutionEndedEvent;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\EventSubscriber\UploadedFileSubscriber;
use CleverAge\UiProcessBundle\Http\Model\HttpProcessExecution;
use CleverAge\UiProcessBundle\Http\ValueResolver\HttpProcessExecuteValueResolver;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Manager\UploadedFileManager;
use CleverAge\UiProcessBundle\Message\ProcessExecuteHandler;
use CleverAge\UiProcessBundle\Message\ProcessExecuteMessage;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use CleverAge\UiProcessBundle\Notifier\ProcessExecutionNotifier;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Routing\DeprecatedEasyAdminRouteAliasLoader;
use CleverAge\UiProcessBundle\StopChecker\ProcessExecutionStopChecker;
use CleverAge\UiProcessBundle\Twig\Extension\LogLevelExtension;
use CleverAge\UiProcessBundle\Twig\Extension\MD5Extension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExecutionExtension;
use CleverAge\UiProcessBundle\Twig\Extension\ProcessExtension;
use CleverAge\UiProcessBundle\Validator\IsValidProcessCodeValidator;
use Composer\InstalledVersions;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Event\WorkerMessageFailedEvent;
use Symfony\Component\Messenger\EventListener\StopWorkerOnMessageLimitListener;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\SentToFailureTransportStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Symfony\Component\Messenger\Worker;

/**
 * ProcessExecuteMessage consumed by a Messenger worker: the uploaded input is kept while the message will be retried.
 */
#[CoversClass(ProcessExecuteHandler::class)]
#[UsesClass(UploadedFileManager::class)]
#[CoversClass(UploadedFileSubscriber::class)]
#[UsesClass(UploadedFileSubscriberPass::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CheckDoctrineLazyGhostObjectsPass::class)]
#[UsesClass(UploadDirectoryParameterPass::class)]
#[UsesClass(LoginThrottlingPass::class)]
#[UsesClass(LogRecordCrudController::class)]
#[UsesClass(ProcessDashboardController::class)]
#[UsesClass(ProcessExecutionCrudController::class)]
#[UsesClass(ProcessScheduleCrudController::class)]
#[UsesClass(UserCrudController::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(DeprecatedEasyAdminRouteAliasLoader::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessExecution::class)]
#[UsesClass(User::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(HttpProcessExecution::class)]
#[UsesClass(HttpProcessExecuteValueResolver::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(ProcessExecutionManager::class)]
#[UsesClass(ProcessExecuteMessage::class)]
#[UsesClass(DoctrineProcessHandler::class)]
#[UsesClass(ProcessHandler::class)]
#[UsesClass(ProcessExecutionRepository::class)]
#[UsesClass(LogLevelExtension::class)]
#[UsesClass(MD5Extension::class)]
#[UsesClass(ProcessExecutionExtension::class)]
#[UsesClass(ProcessExtension::class)]
#[UsesClass(IsValidProcessCodeValidator::class)]
#[UsesClass(ProcessExecutionEndedEvent::class)]
#[UsesClass(NotificationTrigger::class)]
#[UsesClass(ProcessExecutionNotifier::class)]
#[UsesClass(ProcessExecutionStopChecker::class)]
class ProcessExecuteWorkerTest extends FunctionalTestCase
{
    #[DataProvider('provideConsume')]
    public function testConsume(string $code, bool $deleteInput, bool $retry, bool $deleted): void
    {
        $uploadDirectory = static::getContainer()->getParameter('cleverage_ui_process.upload_directory');
        self::assertIsString($uploadDirectory);
        $input = $uploadDirectory.'/worker_input.csv';
        (new Filesystem())->dumpFile($input, 'data');
        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.execute_process');
        $transport->send(new Envelope(new ProcessExecuteMessage($code, $input, [], $deleteInput)));

        $eventDispatcher = new EventDispatcher();
        /** @var EventSubscriberInterface $subscriber */
        $subscriber = static::getContainer()->get('cleverage_ui_process.event_subscriber.uploaded_file');
        $eventDispatcher->addSubscriber($subscriber);
        $eventDispatcher->addSubscriber(new StopWorkerOnMessageLimitListener(1));
        if ($retry) {
            // As the retry strategy of a transport with retries
            $eventDispatcher->addListener(WorkerMessageFailedEvent::class, static function (WorkerMessageFailedEvent $event): void {
                $event->setForRetry();
            }, 100);
        }
        /** @var MessageBusInterface $bus */
        $bus = static::getContainer()->get('messenger.default_bus');
        (new Worker(['execute_process' => $transport], $bus, $eventDispatcher))->run(['sleep' => 0]);

        // The message has been consumed (acknowledged or rejected)
        self::assertCount(1, [...$transport->getAcknowledged(), ...$transport->getRejected()]);
        $exists = file_exists($input);
        (new Filesystem())->remove($input);
        self::assertSame(!$deleted, $exists);
    }

    /**
     * @return iterable<string, array{string, bool, bool, bool}>
     */
    public static function provideConsume(): iterable
    {
        yield 'uploaded, success' => ['test.process', true, false, true];
        yield 'uploaded, failure without retry' => ['test.failing', true, false, true];
        yield 'uploaded, failure with retry' => ['test.failing', true, true, false];
        yield 'given by its path, success' => ['test.process', false, false, false];
        yield 'given by its path, failure' => ['test.failing', false, false, false];
    }

    /**
     * With a failure transport (Symfony recipe): the file of a message sent to the failure transport is kept for
     * messenger:failed:retry, and deleted when the message fails again there (dropped by Symfony).
     */
    public function testFailureTransport(): void
    {
        $this->createFailureTransportClient();
        $input = $this->createInput();
        $transport = $this->getTransport('execute_process');
        $failureTransport = $this->getTransport('failed');
        $transport->send(new Envelope(new ProcessExecuteMessage('test.failing', $input, [], true)));

        $this->consume('execute_process');

        self::assertCount(1, $failureTransport->getSent());
        self::assertFileExists($input);

        // messenger:failed:retry, failing again
        $this->consume('failed');

        self::assertCount(1, $failureTransport->getSent(), 'Not sent again to the failure transport');
        self::assertFileDoesNotExist($input);
    }

    public function testFailureTransportRetrySucceeds(): void
    {
        $this->createFailureTransportClient();
        $input = $this->createInput();
        $this->getTransport('failed')->send(
            (new Envelope(new ProcessExecuteMessage('test.process', $input, [], true)))
                ->with(new SentToFailureTransportStamp('execute_process'))
        );

        $this->consume('failed');

        self::assertFileDoesNotExist($input);
    }

    private function createFailureTransportClient(): void
    {
        $version = InstalledVersions::getVersion('symfony/messenger') ?? '';
        if (version_compare($version, '6.4.5', '<')) {
            self::markTestSkipped('Before Symfony 6.4.5, a message already sent to a failure transport is sent again.');
        }
        // Messenger failure transport, as configured by the Symfony recipe
        $this->createTestClient(['framework' => ['messenger' => [
            'failure_transport' => 'failed',
            'transports' => ['failed' => ['dsn' => 'in-memory://', 'retry_strategy' => ['max_retries' => 0]]],
        ]]]);
    }

    private function createInput(): string
    {
        $uploadDirectory = static::getContainer()->getParameter('cleverage_ui_process.upload_directory');
        self::assertIsString($uploadDirectory);
        $input = $uploadDirectory.'/worker_input.csv';
        (new Filesystem())->dumpFile($input, 'data');

        return $input;
    }

    private function getTransport(string $name): InMemoryTransport
    {
        $transport = static::getContainer()->get('messenger.transport.'.$name);
        self::assertInstanceOf(InMemoryTransport::class, $transport);

        return $transport;
    }

    /**
     * Consumes one message with the event dispatcher of the application, as messenger:consume.
     */
    private function consume(string $transportName): void
    {
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = static::getContainer()->get('event_dispatcher');
        $stopListener = new StopWorkerOnMessageLimitListener(1);
        $eventDispatcher->addSubscriber($stopListener);
        /** @var MessageBusInterface $bus */
        $bus = static::getContainer()->get('messenger.default_bus');
        try {
            (new Worker([$transportName => $this->getTransport($transportName)], $bus, $eventDispatcher))->run(['sleep' => 0]);
        } finally {
            $eventDispatcher->removeSubscriber($stopListener);
        }
    }
}
