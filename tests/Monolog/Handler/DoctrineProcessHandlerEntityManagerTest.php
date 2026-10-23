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
use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckDoctrineLazyGhostObjectsPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\LoginThrottlingPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadDirectoryParameterPass;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\Enum\ProcessExecutionStatus;
use CleverAge\UiProcessBundle\Entity\LogRecord;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
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

/**
 * DoctrineProcessHandler with the entity manager of the test application (SQLite).
 */
#[CoversClass(DoctrineProcessHandler::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CheckDoctrineLazyGhostObjectsPass::class)]
#[UsesClass(UploadDirectoryParameterPass::class)]
#[UsesClass(LoginThrottlingPass::class)]
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
