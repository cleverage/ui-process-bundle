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
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;

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
        $entityManager = $this->createMock(EntityManagerInterface::class);
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
        $entityManager = $this->createMock(EntityManagerInterface::class);
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
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::once())->method('persist')->with(self::isInstanceOf(LogRecord::class));
        $entityManager->expects(self::once())->method('flush');

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        $handler->handle($this->createRecord(Level::Warning, 'message'));
        unset($handler);
    }

    public function testRecordsAreDroppedWithoutProcessExecution(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
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
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('isOpen')->willReturn(false);
        $entityManager->expects(self::never())->method('flush');

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        $handler->flush();
    }

    public function testOpenEntityManagerWithoutRecords(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('isOpen')->willReturn(true);
        $entityManager->expects(self::once())->method('flush');

        $handler = $this->createHandler($entityManager, new ProcessExecution('test.process', 'test.log'));
        $handler->flush();
        $handler->disable();
    }

    public function testDisabledHandler(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
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
