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

namespace CleverAge\UiProcessBundle\Tests\StopChecker;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessBundle\Model\ProcessHistory;
use CleverAge\ProcessBundle\Model\ProcessState;
use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckDoctrineLazyGhostObjectsPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\LoginThrottlingPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadDirectoryParameterPass;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\EventSubscriber\ProcessEventSubscriber;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Routing\DeprecatedEasyAdminRouteAliasLoader;
use CleverAge\UiProcessBundle\StopChecker\ProcessExecutionStopChecker;
use CleverAge\UiProcessBundle\Tests\App\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

#[CoversClass(ProcessExecutionStopChecker::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CheckDoctrineLazyGhostObjectsPass::class)]
#[UsesClass(UploadDirectoryParameterPass::class)]
#[UsesClass(LoginThrottlingPass::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(DeprecatedEasyAdminRouteAliasLoader::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(ProcessEventSubscriber::class)]
#[UsesClass(ProcessExecution::class)]
#[UsesClass(ProcessExecutionManager::class)]
#[UsesClass(ProcessExecutionRepository::class)]
class ProcessExecutionStopCheckerTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    private ProcessExecutionManager $processExecutionManager;

    private ProcessState $state;

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

        $this->processExecutionManager = new ProcessExecutionManager(new ProcessExecutionRepository($entityManager));
        $processConfiguration = new ProcessConfiguration('test.process', []);
        $this->state = new ProcessState($processConfiguration, new ProcessHistory($processConfiguration));
    }

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    public function testWithoutProcessExecution(): void
    {
        self::assertFalse($this->createChecker(0)->shouldStop($this->state));
    }

    public function testHeartbeatAndStopRequest(): void
    {
        $processExecution = $this->startProcessExecution();
        $checker = $this->createChecker(0);

        $before = new \DateTimeImmutable('-1 second');
        self::assertFalse($checker->shouldStop($this->state));
        $heartbeat = $this->fetchHeartbeat($processExecution);
        self::assertNotNull($heartbeat);
        self::assertGreaterThanOrEqual($before, $heartbeat);

        // Requested from the UI (another PHP process)
        $this->entityManager->getConnection()->executeStatement(
            'UPDATE process_execution SET stop_requested_at = CURRENT_TIMESTAMP WHERE id = ?',
            [$processExecution->getId()]
        );

        self::assertTrue($checker->shouldStop($this->state));
        // Kept until the process has failed, without query
        $this->entityManager->getConnection()->executeStatement('UPDATE process_execution SET stop_requested_at = NULL');
        self::assertTrue($checker->shouldStop($this->state));
    }

    public function testChecksAreThrottled(): void
    {
        $processExecution = $this->startProcessExecution();
        $checker = $this->createChecker(3600);

        self::assertFalse($checker->shouldStop($this->state));
        self::assertNotNull($this->fetchHeartbeat($processExecution));

        $this->entityManager->getConnection()->executeStatement('UPDATE process_execution SET heartbeat_at = NULL, stop_requested_at = CURRENT_TIMESTAMP');

        // Not checked again before the interval
        self::assertFalse($checker->shouldStop($this->state));
        self::assertNull($this->fetchHeartbeat($processExecution));

        // A new process execution is checked right away
        $this->processExecutionManager->unsetProcessExecution('test.process');
        $this->startProcessExecution();
        $this->entityManager->getConnection()->executeStatement('UPDATE process_execution SET stop_requested_at = CURRENT_TIMESTAMP');
        self::assertTrue($checker->shouldStop($this->state));
    }

    public function testDatabaseErrorDoesNotStopTheProcess(): void
    {
        $this->startProcessExecution();
        $this->entityManager->getConnection()->executeStatement('ALTER TABLE process_execution DROP COLUMN heartbeat_at');

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())
            ->method('warning')
            ->with(
                'Unable to update the heartbeat of the process execution, nor to read its stop request.',
                self::callback(static fn (array $context): bool => 1 === $context['process_execution'] && \is_string($context['error']))
            );
        $checker = $this->createChecker(0, $logger);

        self::assertFalse($checker->shouldStop($this->state));
        // Logged once
        self::assertFalse($checker->shouldStop($this->state));
    }

    private function createChecker(int $heartbeatInterval, ?LoggerInterface $logger = null): ProcessExecutionStopChecker
    {
        return new ProcessExecutionStopChecker($this->processExecutionManager, $this->entityManager, $heartbeatInterval, $logger);
    }

    private function startProcessExecution(): ProcessExecution
    {
        $processExecution = new ProcessExecution('test.process', 'test.log');
        $this->processExecutionManager->setCurrentProcessExecution($processExecution)->save();

        return $processExecution;
    }

    private function fetchHeartbeat(ProcessExecution $processExecution): ?\DateTimeImmutable
    {
        $value = $this->entityManager->getConnection()->fetchOne(
            'SELECT heartbeat_at FROM process_execution WHERE id = ?',
            [$processExecution->getId()]
        );

        return \is_string($value) ? new \DateTimeImmutable($value) : null;
    }
}
