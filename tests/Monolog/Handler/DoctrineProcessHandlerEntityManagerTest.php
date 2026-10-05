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
 * DoctrineProcessHandler with the entity manager of the test application (SQLite).
 */
#[CoversClass(DoctrineProcessHandler::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(LogRecord::class)]
#[UsesClass(ProcessExecution::class)]
#[UsesClass(ProcessExecutionManager::class)]
#[UsesClass(ProcessExecutionRepository::class)]
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
}
