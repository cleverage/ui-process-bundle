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

namespace CleverAge\UiProcessBundle\Tests\Repository;

use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\LogRecord;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Tests\App\TestKernel;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(ProcessExecutionRepository::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(LogRecord::class)]
#[UsesClass(ProcessExecution::class)]
class ProcessExecutionRepositoryTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private ProcessExecutionRepository $repository;

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

        $this->repository = new ProcessExecutionRepository($entityManager);
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testSave(): void
    {
        $processExecution = new ProcessExecution('test.process', 'test.log', ['foo' => 'bar']);
        $this->repository->save($processExecution);

        self::assertNotNull($processExecution->getId());
        $this->entityManager->clear();

        $saved = $this->repository->find($processExecution->getId());
        self::assertInstanceOf(ProcessExecution::class, $saved);
        self::assertNotSame($processExecution, $saved);
        self::assertSame('test.process', $saved->getCode());
        self::assertSame('test.log', $saved->logFilename);
        self::assertSame(['foo' => 'bar'], $saved->getContext());
    }

    public function testGetLastProcessExecution(): void
    {
        self::assertNull($this->repository->getLastProcessExecution('test.process'));

        $old = $this->createProcessExecution('test.process', '2024-01-01 10:00:00');
        $last = $this->createProcessExecution('test.process', '2024-01-03 10:00:00');
        $this->createProcessExecution('test.process', '2024-01-02 10:00:00');
        $this->createProcessExecution('other.process', '2024-01-04 10:00:00');
        $this->entityManager->clear();

        $lastProcessExecution = $this->repository->getLastProcessExecution('test.process');
        self::assertInstanceOf(ProcessExecution::class, $lastProcessExecution);
        self::assertSame($last, $lastProcessExecution->getId());
        self::assertNotSame($old, $last);
        self::assertNull($this->repository->getLastProcessExecution('unknown.process'));
    }

    public function testGetLastProcessExecutionWithAQuoteInTheCode(): void
    {
        $id = $this->createProcessExecution("it's.process", '2024-01-01 10:00:00');
        $this->createProcessExecution('its.process', '2024-01-02 10:00:00');

        self::assertSame($id, $this->repository->getLastProcessExecution("it's.process")?->getId());
    }

    public function testHasLogs(): void
    {
        $withLogs = new ProcessExecution('test.process', 'with_logs.log');
        $withoutLogs = new ProcessExecution('test.process', 'without_logs.log');
        $this->repository->save($withoutLogs);
        $this->entityManager->persist(new LogRecord(
            new \Monolog\LogRecord(new \DateTimeImmutable(), 'cleverage_process', Level::Info, 'message'),
            $withLogs
        ));
        $this->entityManager->flush();

        self::assertTrue($this->repository->hasLogs($withLogs));
        self::assertFalse($this->repository->hasLogs($withoutLogs));
    }

    /**
     * Saves a process execution started at the given date (the start date is always "now" in the entity).
     */
    private function createProcessExecution(string $code, string $startDate): int
    {
        $processExecution = new ProcessExecution($code, uniqid('', true).'.log');
        $this->repository->save($processExecution);
        $id = (int) $processExecution->getId();

        $this->entityManager->getConnection()->update(
            'process_execution',
            ['start_date' => new \DateTimeImmutable($startDate)],
            ['id' => $id],
            ['start_date' => Types::DATETIME_IMMUTABLE]
        );

        return $id;
    }
}
