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

use CleverAge\ProcessBundle\Event\ProcessEvent;
use CleverAge\UiProcessBundle\CacheWarmer\LaunchFormsCacheWarmer;
use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckDoctrineLazyGhostObjectsPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\LoginThrottlingPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\MonologFilterLevelPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadDirectoryParameterPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadedFileSubscriberPass;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\Enum\ProcessExecutionStatus;
use CleverAge\UiProcessBundle\Entity\LogRecord;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\Form\LaunchFormOptionsResolver;
use CleverAge\UiProcessBundle\Form\Type\LaunchType;
use CleverAge\UiProcessBundle\Form\Type\ProcessContextType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Routing\DeprecatedEasyAdminRouteAliasLoader;
use CleverAge\UiProcessBundle\Tests\App\TestKernel;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Events;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use Monolog\Handler\TestHandler;
use Monolog\Level;
use Monolog\Logger;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Process\Process;

/**
 * DoctrineProcessHandler with the entity manager of the test application (SQLite).
 */
#[CoversClass(DoctrineProcessHandler::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CheckDoctrineLazyGhostObjectsPass::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(LaunchFormOptionsResolver::class)]
#[UsesClass(ProcessContextType::class)]
#[UsesClass(LaunchType::class)]
#[UsesClass(LaunchFormsCacheWarmer::class)]
#[UsesClass(UploadDirectoryParameterPass::class)]
#[UsesClass(LoginThrottlingPass::class)]
#[UsesClass(UploadedFileSubscriberPass::class)]
#[UsesClass(MonologFilterLevelPass::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(DeprecatedEasyAdminRouteAliasLoader::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(LogRecord::class)]
#[UsesClass(ProcessExecution::class)]
#[UsesClass(ProcessExecutionManager::class)]
#[UsesClass(ProcessExecutionRepository::class)]
#[UsesClass(ProcessHandler::class)]
#[UsesClass(User::class)]
class DoctrineProcessHandlerEntityManagerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel(['debug' => false]);

        /** @var EntityManagerInterface $entityManager */
        $entityManager = static::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;
        $schemaTool = new SchemaTool($entityManager);
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testWrittenLogRecordsAreDetached(): void
    {
        $processExecutionManager = new ProcessExecutionManager(new ProcessExecutionRepository($this->entityManager));
        $processExecution = new ProcessExecution('test.process', 'test.log');
        $processExecutionManager->setCurrentProcessExecution($processExecution)->save();
        $handler = new DoctrineProcessHandler();
        $handler->setEntityManager($this->entityManager);
        $handler->setProcessExecutionManager($processExecutionManager);

        for ($i = 0; $i < 3; ++$i) {
            for ($j = 0; $j < 100; ++$j) {
                $handler->handle(new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Warning, 'message '.$j));
            }
            $handler->flush();

            // The identity map does not grow with the written log records, the process execution is still managed
            self::assertSame([], $this->entityManager->getUnitOfWork()->getIdentityMap()[LogRecord::class] ?? []);
            self::assertTrue($this->entityManager->contains($processExecution));
        }
        $handler->disable();

        // The process execution is updated, not inserted again
        $processExecution->setStatus(ProcessExecutionStatus::Finish);
        $processExecution->end();
        $processExecutionManager->save();

        $connection = $this->entityManager->getConnection();
        self::assertSame(
            [['id' => $processExecution->getId(), 'status' => 'finish']],
            $connection->fetchAllAssociative('SELECT id, status FROM process_execution')
        );
        self::assertSame(
            [['process_execution_id' => $processExecution->getId(), 'count' => 300]],
            $connection->fetchAllAssociative('SELECT process_execution_id, COUNT(*) AS count FROM log_record GROUP BY process_execution_id')
        );
    }

    public function testInvalidUtf8AndLongMessagesAreStored(): void
    {
        $processExecutionManager = new ProcessExecutionManager(new ProcessExecutionRepository($this->entityManager));
        $processExecutionManager->setCurrentProcessExecution(new ProcessExecution('test.process', 'test.log'))->save();
        $handler = new DoctrineProcessHandler();
        $handler->setEntityManager($this->entityManager);
        $handler->setProcessExecutionManager($processExecutionManager);

        $handler->handle(new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Warning, "caf\xE9", ['input' => "cr\xE8me"]));
        $handler->handle(new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Warning, str_repeat('👨‍👩‍👧‍👦', 100)));
        $handler->flush();
        $handler->disable();

        // SQLite does not check the length of the columns (MySQL and PostgreSQL count code points)
        /** @var list<array{message: string, context: string}> $logRecords */
        $logRecords = $this->entityManager->getConnection()->fetchAllAssociative('SELECT message, context FROM log_record ORDER BY id');
        self::assertCount(2, $logRecords);
        self::assertSame('caf?', $logRecords[0]['message']);
        self::assertSame(['input' => "cr\u{FFFD}me"], json_decode($logRecords[0]['context'], true));
        self::assertSame(512, mb_strlen($logRecords[1]['message']));
    }

    /**
     * The failure to store the logs does not prevent saving the status of the process execution.
     */
    public function testFlushFailureAtTheEndOfTheProcess(): void
    {
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $manager = new ProcessExecutionManager(new ProcessExecutionRepository($this->entityManager, $registry));
        $handler = new DoctrineProcessHandler();
        $handler->setEntityManager($this->entityManager);
        $handler->setProcessExecutionManager($manager);
        $logger = new TestHandler();
        $handler->setLogger(new Logger('cleverage_process', [$logger]));
        $processHandler = new ProcessHandler(sys_get_temp_dir(), $manager);
        $processHandler->setFilename('test.process/test.log');
        $subscriber = new ProcessEventSubscriber($processHandler, $handler, $manager);
        $connection = $this->entityManager->getConnection();

        $event = new ProcessEvent('test.process');
        $subscriber->onProcessStart($event);
        $handler->handle(new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Warning, 'written'));
        $handler->flush();
        $handler->handle(new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Warning, 'lost'));

        // The insertion of the log records fails (Doctrine closes the entity manager)
        $connection->executeStatement('ALTER TABLE log_record RENAME TO log_record_renamed');
        try {
            // ProcessEvent::EVENT_PROCESS_ENDED listeners
            $subscriber->flushDoctrineLogs($event);
            // Reset right away for the process tasks
            self::assertTrue($this->entityManager->isOpen());
            $subscriber->success($event);
        } finally {
            $connection->executeStatement('ALTER TABLE log_record_renamed RENAME TO log_record');
        }

        self::assertSame(
            [['code' => 'test.process', 'status' => 'finish']],
            $connection->fetchAllAssociative('SELECT code, status FROM process_execution')
        );
        self::assertSame(['written'], $connection->fetchFirstColumn('SELECT message FROM log_record ORDER BY id'));
        self::assertTrue($logger->hasErrorThatContains('1 log record(s) could not be stored in database'));
        self::assertTrue($this->entityManager->isOpen());

        // The failed records are not written again
        $handler->flush();
        self::assertSame(['written'], $connection->fetchFirstColumn('SELECT message FROM log_record ORDER BY id'));
        $handler->disable();
    }

    /**
     * The entity manager is shared with the process tasks: the errors of their pending changes are not caught, as
     * before.
     */
    public function testErrorOfAPendingTaskEntityIsNotCaught(): void
    {
        $handler = $this->createHandlerWithLogger($logger = new TestHandler());
        $this->entityManager->persist((new User())->setEmail('user@example.com'));
        $this->entityManager->flush();
        // Persisted by a task, not flushed: violates the unique constraint
        $this->entityManager->persist((new User())->setEmail('user@example.com'));

        $handler->handle(new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Warning, 'message'));
        try {
            $handler->flush();
            self::fail('The error of the task entity is not caught');
        } catch (UniqueConstraintViolationException) {
        }
        self::assertSame([], $logger->getRecords());
        $handler->setLogger(null);
        $handler->disable();
    }

    public function testErrorOfAPendingTaskEntityWithoutLogRecordsIsNotCaught(): void
    {
        $handler = $this->createHandlerWithLogger(new TestHandler());
        $this->entityManager->persist((new User())->setEmail('user@example.com'));
        $this->entityManager->flush();
        $this->entityManager->persist((new User())->setEmail('user@example.com'));

        $this->expectException(UniqueConstraintViolationException::class);
        try {
            $handler->flush();
        } finally {
            $handler->setLogger(null);
            $handler->disable();
        }
    }

    /**
     * A failure before the transaction (the entity manager stays open), e.g. an application listener rejecting an
     * entity of a task.
     */
    public function testFailureOfAnOnFlushListenerOnATaskEntityIsNotCaught(): void
    {
        $handler = $this->createHandlerWithLogger($logger = new TestHandler());
        $this->entityManager->getEventManager()->addEventListener([Events::onFlush], $listener = new class {
            public function onFlush(OnFlushEventArgs $args): void
            {
                foreach ($args->getObjectManager()->getUnitOfWork()->getScheduledEntityInsertions() as $entity) {
                    if ($entity instanceof User) {
                        throw new \DomainException('Invalid user');
                    }
                }
            }
        });
        $this->entityManager->persist((new User())->setEmail('user@example.com'));

        $handler->handle(new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Warning, 'message'));
        try {
            $handler->flush();
            self::fail('The error of the task entity is not caught');
        } catch (\DomainException $exception) {
            self::assertSame('Invalid user', $exception->getMessage());
        } finally {
            $this->entityManager->getEventManager()->removeEventListener([Events::onFlush], $listener);
        }
        self::assertSame([], $logger->getRecords());
        $handler->setLogger(null);
        $handler->disable();
    }

    public function testErrorOfAnotherProcessExecutionChangedByATaskIsNotCaught(): void
    {
        $other = new ProcessExecution('other.process', 'other.log');
        $this->entityManager->persist($other);
        $this->entityManager->flush();
        $handler = $this->createHandlerWithLogger($logger = new TestHandler());
        $this->entityManager->getEventManager()->addEventListener([Events::onFlush], $listener = new class {
            public function onFlush(OnFlushEventArgs $args): void
            {
                foreach ($args->getObjectManager()->getUnitOfWork()->getScheduledEntityUpdates() as $entity) {
                    if ($entity instanceof ProcessExecution && 'other.process' === $entity->getCode()) {
                        throw new \DomainException('Invalid process execution');
                    }
                }
            }
        });
        $other->setStatus(ProcessExecutionStatus::Failed); // by a task

        $handler->handle(new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Warning, 'message'));
        try {
            $handler->flush();
            self::fail('The error of the task entity is not caught');
        } catch (\DomainException $exception) {
            self::assertSame('Invalid process execution', $exception->getMessage());
        } finally {
            $this->entityManager->getEventManager()->removeEventListener([Events::onFlush], $listener);
        }
        self::assertSame([], $logger->getRecords());
        $handler->setLogger(null);
        $handler->disable();
    }

    /**
     * The failure is reported even when the entity manager cannot be reset (the database is still unavailable).
     */
    public function testFailureIsReportedWhenTheDatabaseIsUnavailable(): void
    {
        $handler = $this->createHandlerWithLogger($logger = new TestHandler());
        $connection = $this->entityManager->getConnection();
        $handler->handle(new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Warning, 'message'));
        $connection->executeStatement('ALTER TABLE log_record RENAME TO log_record_renamed');
        $connection->executeStatement('ALTER TABLE process_execution RENAME TO process_execution_renamed');
        try {
            // The flush fails and closes the entity manager, which is reset: the process execution cannot be read again
            $handler->flush();
        } finally {
            $connection->executeStatement('ALTER TABLE log_record_renamed RENAME TO log_record');
            $connection->executeStatement('ALTER TABLE process_execution_renamed RENAME TO process_execution');
        }
        self::assertTrue($logger->hasErrorThatContains('1 log record(s) could not be stored in database'));
        $handler->setLogger(null);
        $handler->disable();
    }

    /**
     * The log records counted in the report are written right away, with the buffered ones, when no change of the tasks
     * is pending.
     */
    public function testRecordCountedInTheReportIsWrittenRightAway(): void
    {
        $handler = $this->createHandlerWithLogger(new TestHandler());
        $handler->setReportIncrementLevel('Warning');
        $handler->handle($this->createRecord(Level::Info, 'info'));
        self::assertSame([], $this->getStoredMessages());

        $handler->handle($this->createRecord(Level::Warning, 'warning'));
        self::assertSame(['info', 'warning'], $this->getStoredMessages());

        // A managed entity of a task: its changes are not computed, the record waits for the next writing
        $user = (new User())->setEmail('user@example.com');
        $this->entityManager->persist($user);
        $this->entityManager->flush();
        $handler->handle($this->createRecord(Level::Error, 'error'));
        self::assertSame(['info', 'warning'], $this->getStoredMessages());

        $this->entityManager->detach($user);
        $handler->handle($this->createRecord(Level::Error, 'other error'));
        self::assertSame(['info', 'warning', 'error', 'other error'], $this->getStoredMessages());
        $handler->disable();
    }

    /**
     * Below the Error level, a log record counted in the report is written right away at most every interval: a process
     * logging many warnings is not slowed down by a writing (and a commit) per warning. The errors are always written
     * right away, and the interval starts again after a reset (e.g. between two messages of a worker).
     */
    public function testRecordCountedInTheReportIsWrittenRightAwayAtMostEveryInterval(): void
    {
        $handler = $this->createHandlerWithLogger(new TestHandler());
        $handler->setReportIncrementLevel('Warning');
        $handler->setWriteRightAwayInterval(3600);
        $handler->handle($this->createRecord(Level::Warning, 'first warning'));
        self::assertSame(['first warning'], $this->getStoredMessages());

        $handler->handle($this->createRecord(Level::Warning, 'second warning'));
        $handler->handle($this->createRecord(Level::Notice, 'notice'));
        self::assertSame(['first warning'], $this->getStoredMessages(), 'Written with the next ones');

        $handler->handle($this->createRecord(Level::Error, 'error'));
        self::assertSame(['first warning', 'second warning', 'notice', 'error'], $this->getStoredMessages());

        $handler->handle($this->createRecord(Level::Warning, 'third warning'));
        self::assertCount(4, $this->getStoredMessages());

        $handler->reset();
        $handler->handle($this->createRecord(Level::Warning, 'fourth warning'));
        self::assertSame(['first warning', 'second warning', 'notice', 'error', 'fourth warning'], $this->getStoredMessages());

        // Each one
        $handler->setWriteRightAwayInterval(0);
        $handler->handle($this->createRecord(Level::Warning, 'fifth warning'));
        self::assertCount(6, $this->getStoredMessages());
        $handler->disable();
    }

    /**
     * Without process execution (e.g. a log record of the process bundle outside of a process), nothing is written.
     */
    public function testRecordCountedInTheReportWithoutProcessExecution(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects(self::never())->method('flush');
        $handler = new DoctrineProcessHandler();
        $handler->setEntityManager($entityManager);
        $handler->setProcessExecutionManager(new ProcessExecutionManager(new ProcessExecutionRepository($this->entityManager)));
        $handler->setReportIncrementLevel('Warning');

        $handler->handle($this->createRecord(Level::Error, 'error'));
        $handler->disable();
    }

    /**
     * The shutdown function writes the buffered log records and the report of the current process execution (after a
     * fatal error), unless changes of the tasks are pending.
     */
    public function testBufferedRecordsAndReportAreWrittenOnShutdown(): void
    {
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $manager = new ProcessExecutionManager(new ProcessExecutionRepository($this->entityManager, $registry));
        $handler = new DoctrineProcessHandler();
        $handler->setEntityManager($this->entityManager);
        $handler->setProcessExecutionManager($manager);
        $handler->onShutdown(); // no process execution: nothing to do

        $manager->setCurrentProcessExecution(new ProcessExecution('test.process', 'test.log'))->save();
        $handler->handle($this->createRecord(Level::Warning, 'warning'));
        $manager->increment('Warning');
        // Persisted by a task, not flushed: neither written nor the log records
        $user = (new User())->setEmail('user@example.com');
        $this->entityManager->persist($user);
        $handler->onShutdown();
        self::assertSame([], $this->getStoredMessages());

        $this->entityManager->detach($user);
        $handler->onShutdown();
        self::assertSame(['warning'], $this->getStoredMessages());
        self::assertSame('{"Warning":1}', $this->entityManager->getConnection()->fetchOne('SELECT report FROM process_execution'));
        self::assertSame([], $this->entityManager->getConnection()->fetchFirstColumn('SELECT email FROM process_user'));

        // Its failures are ignored
        $this->entityManager->getConnection()->executeStatement('DROP TABLE log_record');
        $handler->handle($this->createRecord(Level::Info, 'info'));
        $handler->setLogger(null);
        $handler->onShutdown();
        $handler->disable();
        $handler->onShutdown();
    }

    /**
     * A process execution ended by a fatal error (maximum execution time exceeded), in a PHP process of its own: its
     * buffered log records and its report are written on shutdown.
     */
    public function testFatalErrorDuringAProcess(): void
    {
        $output = $this->runFatalErrorDuringAProcess('time');

        self::assertStringContainsString('Maximum execution time', $output);
        $connection = $this->entityManager->getConnection();
        self::assertSame(['before the warning', 'warning', 'after the warning'], $this->getStoredMessages());
        self::assertSame('{"Warning":1}', $connection->fetchOne('SELECT report FROM process_execution'));
        // Neither ended nor failed
        self::assertSame('started', $connection->fetchOne('SELECT status FROM process_execution'));
    }

    /**
     * Memory limit exceeded: the shutdown function of the Symfony ErrorHandler, called first, fails too (no memory left
     * to load a class), the next ones are not called. The warning, counted in the report, was written right away.
     */
    public function testMemoryLimitExceededDuringAProcess(): void
    {
        $output = $this->runFatalErrorDuringAProcess('memory');

        self::assertStringContainsString('Allowed memory size', $output);
        self::assertSame(['before the warning', 'warning'], \array_slice($this->getStoredMessages(), 0, 2));
    }

    /**
     * The memory limit is raised on shutdown after a fatal error caused by the memory limit, to write the log records.
     */
    public function testMemoryLimitIsRaisedOnShutdown(): void
    {
        $handler = $this->createHandlerWithLogger(new TestHandler());
        $memoryLimit = \ini_get('memory_limit');
        ini_set('memory_limit', '512M');
        try {
            $handler->onShutdown();
            self::assertSame('512M', \ini_get('memory_limit'), 'Only after a fatal error caused by the memory limit');

            @trigger_error('Allowed memory size of 536870912 bytes exhausted (tried to allocate 2097152 bytes)', \E_USER_WARNING);
            $handler->onShutdown();
            self::assertGreaterThanOrEqual(memory_get_usage(true) + 30 * 1024 * 1024, (int) \ini_get('memory_limit'));

            ini_set('memory_limit', '-1');
            $handler->onShutdown();
            self::assertSame('-1', \ini_get('memory_limit'), 'No limit: unchanged');
        } finally {
            ini_set('memory_limit', $memoryLimit);
            error_clear_last();
            $handler->setLogger(null);
            $handler->disable();
        }
    }

    private function runFatalErrorDuringAProcess(string $error): string
    {
        $process = new Process(
            [\PHP_BINARY, __DIR__.'/fatal_error_during_a_process.php', $error],
            null,
            ['UI_PROCESS_BUNDLE_TESTS_RUN_DIR' => TestKernel::getRunDir(), 'SHELL_VERBOSITY' => '0']
        );
        $process->run();
        self::assertFalse($process->isSuccessful());

        return $process->getOutput().$process->getErrorOutput();
    }

    /**
     * @return list<string>
     */
    private function getStoredMessages(): array
    {
        /** @var list<string> $messages */
        $messages = $this->entityManager->getConnection()->fetchFirstColumn('SELECT message FROM log_record ORDER BY id');

        return $messages;
    }

    private function createRecord(Level $level, string $message): \Monolog\LogRecord
    {
        return new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', $level, $message);
    }

    private function createHandlerWithLogger(TestHandler $logger): DoctrineProcessHandler
    {
        /** @var ManagerRegistry $registry */
        $registry = static::getContainer()->get('doctrine');
        $manager = new ProcessExecutionManager(new ProcessExecutionRepository($this->entityManager, $registry));
        $manager->setCurrentProcessExecution(new ProcessExecution('test.process', 'test.log'))->save();
        $handler = new DoctrineProcessHandler();
        $handler->setEntityManager($this->entityManager);
        $handler->setProcessExecutionManager($manager);
        $handler->setLogger(new Logger('cleverage_process', [$logger]));

        return $handler;
    }
}
