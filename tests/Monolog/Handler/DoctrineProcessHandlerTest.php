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

namespace CleverAge\UiProcessBundle\Tests\Monolog\Handler;

use CleverAge\UiProcessBundle\Entity\LogRecord;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

#[CoversClass(DoctrineProcessHandler::class)]
#[UsesClass(LogRecord::class)]
#[UsesClass(ProcessExecution::class)]
#[UsesClass(ProcessExecutionManager::class)]
class DoctrineProcessHandlerTest extends TestCase
{
    public function testRecordsArePersistedOnFlush(): void
    {
        $processExecution = new ProcessExecution('test.process', 'test.log');
        /** @var \ArrayObject<int, LogRecord> $persisted */
        $persisted = new \ArrayObject();
        $entityManager = $this->createEntityManagerMock();
        $entityManager->method('isOpen')->willReturn(true);
        $entityManager->expects(self::exactly(2))
            ->method('persist')
            ->willReturnCallback(static function (object $entity) use ($persisted): void {
                self::assertInstanceOf(LogRecord::class, $entity);
                $persisted[] = $entity;
            });
        $entityManager->expects(self::exactly(2))->method('flush');
        /** @var \ArrayObject<int, object> $detached */
        $detached = new \ArrayObject();
        $entityManager->expects(self::exactly(2))
            ->method('detach')
            ->willReturnCallback(static function (object $entity) use ($detached): void {
                $detached[] = $entity;
            });

        $handler = $this->createHandler($entityManager, $processExecution);
        $handler->handle($this->createRecord(Level::Info, 'first'));
        $handler->handle($this->createRecord(Level::Error, 'second'));
        self::assertCount(0, $persisted, 'Records are kept in memory until the flush');

        $handler->flush();
        self::assertCount(2, $persisted);
        [$first, $second] = $persisted->getArrayCopy();
        self::assertSame('first', $first->message);
        self::assertSame(Level::Info->value, $first->level);
        self::assertSame($processExecution, $first->getProcessExecution());
        self::assertSame('second', $second->message);
        self::assertSame(Level::Error->value, $second->level);
        // Only the persisted log entities are detached after the flush
        self::assertSame($persisted->getArrayCopy(), $detached->getArrayCopy());

        // Records are flushed only once
        $handler->flush();
        self::assertCount(2, $persisted);
        $handler->disable(); // no flush on destruction
    }

    public function testRecordsAreFlushedEvery500Records(): void
    {
        $entityManager = $this->createEntityManagerMock();
        $entityManager->expects(self::exactly(500))->method('persist');
        $entityManager->expects(self::once())->method('flush');

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        for ($i = 0; $i < 501; ++$i) {
            $handler->handle($this->createRecord(Level::Debug, 'message '.$i));
        }
        $handler->disable(); // the 501st record is not flushed on destruction
    }

    public function testRecordsAreFlushedOnDestruction(): void
    {
        $entityManager = $this->createEntityManagerMock();
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(LogRecord::class));
        $entityManager->expects(self::once())->method('flush');

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        $handler->handle($this->createRecord(Level::Warning, 'message'));
        unset($handler);
    }

    public function testRecordsAreDroppedWithoutProcessExecution(): void
    {
        $entityManager = $this->createEntityManagerMock();
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::once())->method('flush');
        $entityManager->expects(self::never())->method('detach');

        $handler = $this->createHandler($entityManager, null);
        $handler->handle($this->createRecord(Level::Info, 'message'));
        $handler->flush();
        $handler->disable();
    }

    public function testClosedEntityManagerWithoutRecords(): void
    {
        $entityManager = $this->createEntityManagerMock();
        $entityManager->method('isOpen')->willReturn(false);
        $entityManager->expects(self::never())->method('flush');

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        $handler->flush();
    }

    public function testOpenEntityManagerWithoutRecords(): void
    {
        $entityManager = $this->createEntityManagerMock();
        $entityManager->method('isOpen')->willReturn(true);
        $entityManager->expects(self::once())->method('flush');

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        $handler->flush();
        $handler->disable();
    }

    public function testDisabledHandler(): void
    {
        $entityManager = $this->createEntityManagerMock();
        $entityManager->expects(self::never())->method('persist');
        $entityManager->expects(self::never())->method('flush');

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        $handler->handle($this->createRecord(Level::Info, 'before'));
        $handler->disable();
        $handler->handle($this->createRecord(Level::Info, 'after'));
        $handler->flush();
    }

    public function testWithoutEntityManagerNorProcessExecutionManager(): void
    {
        $handler = new DoctrineProcessHandler(Level::Info, false);

        self::assertFalse($handler->isHandling($this->createRecord(Level::Debug, 'debug')));
        self::assertTrue($handler->handle($this->createRecord(Level::Info, 'message')), 'The record does not bubble');
        $handler->flush();
    }

    public function testResetDropsTheRecordsNotWritten(): void
    {
        $entityManager = $this->createEntityManagerMock();
        $entityManager->method('isOpen')->willReturn(true);
        $entityManager->expects(self::never())->method('persist');

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        $handler->handle($this->createRecord(Level::Info, 'not written'));
        $handler->reset();
        $handler->flush();
        $handler->disable(); // no flush on destruction
    }

    public function testFlushFailureDropsTheRecordsAndIsReported(): void
    {
        /** @var \ArrayObject<int, object> $persisted */
        $persisted = new \ArrayObject();
        /** @var \ArrayObject<int, object> $detached */
        $detached = new \ArrayObject();
        $entityManager = $this->createEntityManagerMock();
        $entityManager->method('isOpen')->willReturn(true);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use ($persisted): void {
            $persisted[] = $entity;
        });
        $entityManager->method('detach')->willReturnCallback(static function (object $entity) use ($detached): void {
            $detached[] = $entity;
        });
        $failure = new \RuntimeException('Data too long');
        $entityManager->expects(self::exactly(2))
            ->method('flush')
            ->willReturnOnConsecutiveCalls(self::throwException($failure), null);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('error')
            ->with('2 log record(s) could not be stored in database: Data too long', ['exception' => $failure]);

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        $handler->setLogger($logger);
        $handler->handle($this->createRecord(Level::Info, 'first'));
        $handler->handle($this->createRecord(Level::Info, 'second'));
        $handler->flush(); // does not throw

        // Not inserted by the next flush (e.g. saving the process execution) when the entity manager is still open
        self::assertCount(2, $persisted);
        self::assertSame($persisted->getArrayCopy(), $detached->getArrayCopy());

        // The failed records are dropped: only the next ones are written
        $handler->handle($this->createRecord(Level::Info, 'third'));
        $handler->flush();
        self::assertCount(3, $persisted);
        self::assertInstanceOf(LogRecord::class, $persisted[2]);
        self::assertSame('third', $persisted[2]->message);
        $handler->disable();
    }

    /**
     * @return iterable<string, array{array<string, array<mixed>>}>
     */
    public static function provideTaskChanges(): iterable
    {
        $taskEntity = new \stdClass();
        $managed = ['getIdentityMap' => [\stdClass::class => [1 => $taskEntity]]];
        yield 'insertion' => [['getScheduledEntityInsertions' => [$taskEntity]]];
        yield 'update' => [$managed + ['getScheduledEntityUpdates' => [$taskEntity]]];
        yield 'deletion' => [['getScheduledEntityDeletions' => [$taskEntity]]];
        yield 'collection update' => [$managed + ['getScheduledCollectionUpdates' => [$taskEntity]]];
        yield 'collection deletion' => [['getScheduledCollectionDeletions' => [$taskEntity]]];
        $otherProcessExecution = new ProcessExecution('other.process', 'other.log');
        yield 'other process execution' => [['getIdentityMap' => [ProcessExecution::class => [1 => $otherProcessExecution]], 'getScheduledEntityUpdates' => [$otherProcessExecution]]];
    }

    /**
     * The entity manager is shared with the process tasks: the failure may come from their pending changes.
     *
     * @param array<string, array<mixed>> $unitOfWorkResults
     */
    #[DataProvider('provideTaskChanges')]
    public function testFlushFailureWithChangesOfTheTasksIsNotCaught(array $unitOfWorkResults): void
    {
        $entityManager = $this->createEntityManagerMock($unitOfWorkResults);
        $entityManager->method('isOpen')->willReturn(true);
        $failure = new \RuntimeException('Failure of a task entity');
        $entityManager->expects(self::once())->method('flush')->willThrowException($failure);
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::never())->method('error');

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        $handler->setLogger($logger);
        $handler->handle($this->createRecord(Level::Info, 'message'));
        try {
            $handler->flush();
            self::fail('The failure is not caught');
        } catch (\RuntimeException $exception) {
            self::assertSame($failure, $exception);
        }
        $handler->disable();
    }

    /**
     * The current process execution updated (report), entities of the tasks managed without changes.
     */
    public function testFlushFailureWithoutChangesOfTheTasksIsCaught(): void
    {
        $processExecution = new ProcessExecution('test.process', 'test.log');
        $entityManager = $this->createEntityManagerMock([
            'getIdentityMap' => [ProcessExecution::class => [1 => $processExecution], \stdClass::class => [1 => new \stdClass()]],
            'getScheduledEntityUpdates' => [$processExecution],
        ]);
        $entityManager->method('isOpen')->willReturn(true);
        $entityManager->expects(self::once())->method('flush')->willThrowException(new \RuntimeException('Failure'));
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');

        $handler = $this->createHandler($entityManager, $processExecution);
        $handler->setLogger($logger);
        $handler->handle($this->createRecord(Level::Info, 'message'));
        $handler->flush();
        $handler->disable();
    }

    public function testPersistFailureIsCaught(): void
    {
        $entityManager = $this->createEntityManagerMock();
        $entityManager->method('isOpen')->willReturn(true);
        $entityManager->method('persist')->willThrowException(new \RuntimeException('Failure'));
        $entityManager->expects(self::never())->method('flush');
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error')->with('1 log record(s) could not be stored in database: Failure');

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        $handler->setLogger($logger);
        $handler->handle($this->createRecord(Level::Info, 'message'));
        $handler->flush();
        $handler->disable();
    }

    public function testFlushFailureWithoutRecordsIsNotCaught(): void
    {
        $entityManager = $this->createEntityManagerMock();
        $entityManager->method('isOpen')->willReturn(true);
        $entityManager->expects(self::once())->method('flush')->willThrowException(new \RuntimeException('Failure'));

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        $this->expectExceptionObject(new \RuntimeException('Failure'));
        try {
            $handler->flush();
        } finally {
            $handler->disable();
        }
    }

    public function testFlushFailureWithAClosedEntityManager(): void
    {
        $entityManager = $this->createEntityManagerMock();
        $entityManager->method('isOpen')->willReturn(false); // closed by Doctrine after the failure of the flush
        $entityManager->expects(self::once())->method('persist');
        $entityManager->expects(self::once())->method('flush')->willThrowException(new \RuntimeException('Failure'));
        $entityManager->expects(self::never())->method('detach');

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        $handler->handle($this->createRecord(Level::Info, 'message'));
        $handler->flush(); // does not throw, without logger
        $handler->flush(); // nothing to write anymore
        $handler->disable();
    }

    public function testFlushFailureOfThe500thRecordDoesNotFail(): void
    {
        $entityManager = $this->createEntityManagerMock();
        $entityManager->method('isOpen')->willReturn(true);
        $entityManager->expects(self::exactly(1000))->method('persist');
        // Every 500 records again after a failure: the records do not accumulate
        $entityManager->expects(self::exactly(2))
            ->method('flush')
            ->willThrowException(new \RuntimeException('Failure'));

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        for ($i = 0; $i < 1000; ++$i) {
            $handler->handle($this->createRecord(Level::Debug, 'message '.$i));
        }
        $handler->disable();
    }

    public function testReportedFailureIsNotStoredInDatabase(): void
    {
        /** @var \ArrayObject<int, LogRecord> $persisted */
        $persisted = new \ArrayObject();
        $entityManager = $this->createEntityManagerMock();
        $entityManager->method('isOpen')->willReturn(true);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use ($persisted): void {
            self::assertInstanceOf(LogRecord::class, $entity);
            $persisted[] = $entity;
        });
        $entityManager->expects(self::exactly(2))
            ->method('flush')
            ->willReturnOnConsecutiveCalls(self::throwException(new \RuntimeException('Failure')), null);

        // The failure is logged on the channel of the handler (cleverage_process): handled by the handler itself
        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        $otherHandler = new TestHandler();
        $handler->setLogger(new Logger('cleverage_process', [$handler, $otherHandler]));
        $handler->handle($this->createRecord(Level::Info, 'message'));
        $handler->flush();
        $handler->flush();

        // Reported to the other handlers only
        self::assertCount(1, $persisted);
        self::assertTrue($otherHandler->hasErrorThatContains('1 log record(s) could not be stored in database: Failure'));
        $handler->disable();
        $handler->setLogger(null); // circular reference: the handler would be destructed during another test
    }

    /**
     * Entity manager whose unit of work has nothing else to write (doctrine/orm 2: no native return types, a mock
     * would return null).
     *
     * @param array<string, array<mixed>> $unitOfWorkResults results of the methods of the unit of work
     */
    private function createEntityManagerMock(array $unitOfWorkResults = []): EntityManagerInterface&MockObject
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWorkResults += [
            'getScheduledEntityInsertions' => [],
            'getScheduledEntityUpdates' => [],
            'getScheduledEntityDeletions' => [],
            'getScheduledCollectionUpdates' => [],
            'getScheduledCollectionDeletions' => [],
            'getIdentityMap' => [],
        ];
        foreach ($unitOfWorkResults as $method => $result) {
            $unitOfWork->method($method)->willReturn($result);
        }
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);

        return $entityManager;
    }

    private function createHandler(EntityManagerInterface $entityManager, ?ProcessExecution $processExecution): DoctrineProcessHandler
    {
        $processExecutionManager = new ProcessExecutionManager($this->createRepositoryStub());
        if ($processExecution instanceof ProcessExecution) {
            $processExecutionManager->setCurrentProcessExecution($processExecution);
        }

        $handler = new DoctrineProcessHandler();
        $handler->setEntityManager($entityManager);
        $handler->setProcessExecutionManager($processExecutionManager);

        return $handler;
    }

    private function createRecord(Level $level, string $message): \Monolog\LogRecord
    {
        return new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', $level, $message);
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
