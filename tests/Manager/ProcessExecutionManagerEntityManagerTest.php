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

namespace CleverAge\UiProcessBundle\Tests\Manager;

use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\Enum\ProcessExecutionStatus;
use CleverAge\UiProcessBundle\Entity\LogRecord;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Tests\App\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * The entity manager is shared with the process tasks, which may clear it (e.g. ClearEntityManagerTask): the current
 * process execution is then detached. Entity manager of the test application (SQLite).
 */
#[CoversClass(ProcessExecutionManager::class)]
#[CoversClass(ProcessExecutionRepository::class)]
#[CoversClass(DoctrineProcessHandler::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(LogRecord::class)]
#[UsesClass(ProcessExecution::class)]
class ProcessExecutionManagerEntityManagerTest extends KernelTestCase
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

    public function testEntityManagerClearedDuringTheProcess(): void
    {
        $manager = new ProcessExecutionManager(new ProcessExecutionRepository($this->entityManager));
        $handler = new DoctrineProcessHandler();
        $handler->setEntityManager($this->entityManager);
        $handler->setProcessExecutionManager($manager);

        // Process start (ProcessEventSubscriber::onProcessStart())
        $manager->setCurrentProcessExecution(new ProcessExecution('test.process', 'test.log', ['key' => 'value']))->save();
        $handler->handle($this->createRecord('before the clear'));
        $handler->flush();
        $manager->increment('Warning');

        // A process task clears the entity manager
        $this->entityManager->clear();

        $manager->increment('Warning');
        $handler->handle($this->createRecord('after the clear'));
        $handler->flush();

        // Process end (ProcessEventSubscriber::success())
        $manager->getCurrentProcessExecution()?->setStatus(ProcessExecutionStatus::Finish);
        $manager->getCurrentProcessExecution()?->end();
        $manager->save();
        $handler->disable();

        // A single execution, with its final state and all its logs
        $connection = $this->entityManager->getConnection();
        $executions = $connection->fetchAllAssociative('SELECT id, status, end_date, report, context FROM process_execution');
        self::assertCount(1, $executions);
        self::assertSame('finish', $executions[0]['status']);
        self::assertNotNull($executions[0]['end_date']);
        self::assertSame(['Warning' => 2], json_decode((string) $executions[0]['report'], true));
        self::assertSame(['key' => 'value'], json_decode((string) $executions[0]['context'], true));
        self::assertSame(
            ['before the clear', 'after the clear'],
            $connection->fetchFirstColumn('SELECT message FROM log_record WHERE process_execution_id = ? ORDER BY id', [$executions[0]['id']])
        );
        self::assertSame(0, (int) $connection->fetchOne('SELECT COUNT(*) FROM log_record WHERE process_execution_id <> ?', [$executions[0]['id']]));
    }

    public function testProcessExecutionNotDetached(): void
    {
        $repository = new ProcessExecutionRepository($this->entityManager);
        $processExecution = new ProcessExecution('test.process', 'test.log');

        // Not persisted yet, then managed: returned as is
        $notPersisted = $repository->getManaged($processExecution);
        self::assertSame($processExecution, $notPersisted);
        $repository->save($processExecution);
        $managed = $repository->getManaged($processExecution);
        self::assertSame($processExecution, $managed);

        // Removed from the database meanwhile: returned as is
        $this->entityManager->getConnection()->executeStatement('DELETE FROM process_execution');
        $this->entityManager->clear();
        $removed = $repository->getManaged($processExecution);
        self::assertSame($processExecution, $removed);
    }

    private function createRecord(string $message): \Monolog\LogRecord
    {
        return new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Warning, $message);
    }
}
