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

use CleverAge\ProcessBundle\Event\ProcessEvent;
use CleverAge\UiProcessBundle\Entity\Enum\ProcessExecutionStatus;
use CleverAge\UiProcessBundle\Entity\LogRecord;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ProcessEventSubscriber::class)]
#[UsesClass(DoctrineProcessHandler::class)]
#[UsesClass(LogRecord::class)]
#[UsesClass(ProcessExecution::class)]
#[UsesClass(ProcessExecutionManager::class)]
#[UsesClass(ProcessHandler::class)]
class ProcessEventSubscriberTest extends TestCase
{
    public function testSubscribedEvents(): void
    {
        self::assertSame(
            [
                ProcessEvent::EVENT_PROCESS_STARTED => 'onProcessStart',
                ProcessEvent::EVENT_PROCESS_ENDED => [['flushDoctrineLogs', 100], ['success', 100]],
                ProcessEvent::EVENT_PROCESS_FAILED => [['flushDoctrineLogs', 100], ['fail', 100]],
            ],
            ProcessEventSubscriber::getSubscribedEvents()
        );
    }

    public function testProcessStartCreatesTheProcessExecution(): void
    {
        $repository = $this->createMock(ProcessExecutionRepository::class);
        $repository->method('getManaged')->willReturnArgument(0);
        $repository->expects(self::once())->method('save')->with(self::isInstanceOf(ProcessExecution::class));
        $processExecutionManager = new ProcessExecutionManager($repository);
        $processHandler = new ProcessHandler('/var/log/process', $processExecutionManager);

        $this->createSubscriber($processHandler, $processExecutionManager)
            ->onProcessStart(new ProcessEvent('test.process', null, ['foo' => 'bar']));

        self::assertTrue($processHandler->hasFilename());
        self::assertMatchesRegularExpression(
            '#^/var/log/process/test\.process/[0-9a-f-]{36}\.log$#',
            (string) $processHandler->getFilename()
        );

        $processExecution = $processExecutionManager->getCurrentProcessExecution();
        self::assertInstanceOf(ProcessExecution::class, $processExecution);
        self::assertSame('test.process', $processExecution->getCode());
        self::assertSame(basename((string) $processHandler->getFilename()), $processExecution->logFilename);
        self::assertSame(['foo' => 'bar'], $processExecution->getContext());
        self::assertSame(ProcessExecutionStatus::Started, $processExecution->status);
    }

    public function testProcessStartKeepsTheCurrentLogFile(): void
    {
        $processExecutionManager = new ProcessExecutionManager($this->createRepositoryStub());
        $processHandler = new ProcessHandler('/var/log/process', $processExecutionManager);
        $processHandler->setFilename('parent.process/parent.log');

        $this->createSubscriber($processHandler, $processExecutionManager)
            ->onProcessStart(new ProcessEvent('test.process'));

        self::assertSame('/var/log/process/parent.process/parent.log', $processHandler->getFilename());
        self::assertSame('parent.log', $processExecutionManager->getCurrentProcessExecution()?->logFilename);
    }

    public function testSubProcessStartKeepsTheCurrentProcessExecution(): void
    {
        $repository = $this->createMock(ProcessExecutionRepository::class);
        $repository->method('getManaged')->willReturnArgument(0);
        $repository->expects(self::never())->method('save');
        $processExecutionManager = new ProcessExecutionManager($repository);
        $parent = new ProcessExecution('parent.process', 'parent.log');
        $processExecutionManager->setCurrentProcessExecution($parent);

        $this->createSubscriber(new ProcessHandler('/var/log/process', $processExecutionManager), $processExecutionManager)
            ->onProcessStart(new ProcessEvent('test.process'));

        self::assertSame($parent, $processExecutionManager->getCurrentProcessExecution());
    }

    /**
     * @return iterable<string, array{'success'|'fail', ProcessExecutionStatus}>
     */
    public static function provideEnds(): iterable
    {
        yield 'success' => ['success', ProcessExecutionStatus::Finish];
        yield 'fail' => ['fail', ProcessExecutionStatus::Failed];
    }

    /**
     * @param 'success'|'fail' $method
     */
    #[DataProvider('provideEnds')]
    public function testProcessEnd(string $method, ProcessExecutionStatus $expectedStatus): void
    {
        $processExecution = new ProcessExecution('test.process', 'test.log');
        $repository = $this->createMock(ProcessExecutionRepository::class);
        $repository->method('getManaged')->willReturnArgument(0);
        $repository->expects(self::once())->method('save')->with($processExecution);
        $processExecutionManager = new ProcessExecutionManager($repository);
        $processExecutionManager->setCurrentProcessExecution($processExecution);
        $processHandler = new ProcessHandler('/var/log/process', $processExecutionManager);
        $processHandler->setFilename('test.process/test.log');

        $this->createSubscriber($processHandler, $processExecutionManager)->{$method}(new ProcessEvent('test.process'));

        self::assertSame($expectedStatus, $processExecution->status);
        self::assertInstanceOf(\DateTimeImmutable::class, $processExecution->endDate);
        self::assertNull($processExecutionManager->getCurrentProcessExecution());
        self::assertFalse($processHandler->hasFilename());
    }

    /**
     * @param 'success'|'fail' $method
     */
    #[TestWith(['success'])]
    #[TestWith(['fail'])]
    public function testSubProcessEndIsIgnored(string $method): void
    {
        $processExecution = new ProcessExecution('parent.process', 'parent.log');
        $repository = $this->createMock(ProcessExecutionRepository::class);
        $repository->method('getManaged')->willReturnArgument(0);
        $repository->expects(self::never())->method('save');
        $processExecutionManager = new ProcessExecutionManager($repository);
        $processExecutionManager->setCurrentProcessExecution($processExecution);
        $processHandler = new ProcessHandler('/var/log/process', $processExecutionManager);
        $processHandler->setFilename('parent.process/parent.log');

        $this->createSubscriber($processHandler, $processExecutionManager)->{$method}(new ProcessEvent('test.process'));

        self::assertSame(ProcessExecutionStatus::Started, $processExecution->status);
        self::assertNull($processExecution->endDate);
        self::assertSame($processExecution, $processExecutionManager->getCurrentProcessExecution());
        self::assertTrue($processHandler->hasFilename());
    }

    public function testFlushDoctrineLogs(): void
    {
        $processExecutionManager = new ProcessExecutionManager($this->createRepositoryStub());
        $processExecutionManager->setCurrentProcessExecution(new ProcessExecution('test.process', 'test.log'));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(LogRecord::class));
        $entityManager->expects(self::atLeastOnce())->method('flush');

        $doctrineProcessHandler = new DoctrineProcessHandler();
        $doctrineProcessHandler->setEntityManager($entityManager);
        $doctrineProcessHandler->setProcessExecutionManager($processExecutionManager);
        $doctrineProcessHandler->handle(
            new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Info, 'message')
        );

        $subscriber = new ProcessEventSubscriber(
            new ProcessHandler('/var/log/process', $processExecutionManager),
            $doctrineProcessHandler,
            $processExecutionManager
        );
        $subscriber->flushDoctrineLogs(new ProcessEvent('test.process'));
    }

    private function createSubscriber(
        ProcessHandler $processHandler,
        ProcessExecutionManager $processExecutionManager,
    ): ProcessEventSubscriber {
        $doctrineProcessHandler = new DoctrineProcessHandler();
        $doctrineProcessHandler->disable();

        return new ProcessEventSubscriber($processHandler, $doctrineProcessHandler, $processExecutionManager);
    }

    /**
     * Repository returning the given process execution as the managed one (never detached in these tests).
     */
    private function createRepositoryStub(): ProcessExecutionRepository
    {
        $repository = $this->createStub(ProcessExecutionRepository::class);
        $repository->method('getManaged')->willReturnArgument(0);

        return $repository;
    }
}
