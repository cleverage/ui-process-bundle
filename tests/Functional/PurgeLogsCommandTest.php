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

use CleverAge\ProcessBundle\Exception\MissingTaskConfigurationException;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistry;
use CleverAge\ProcessBundle\Registry\ProcessConfigurationRegistryInterface;
use CleverAge\UiProcessBundle\CacheWarmer\LaunchFormsCacheWarmer;
use CleverAge\UiProcessBundle\CleverAgeUiProcessBundle;
use CleverAge\UiProcessBundle\Command\PurgeLogsCommand;
use CleverAge\UiProcessBundle\DependencyInjection\CleverAgeUiProcessExtension;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\CheckDoctrineLazyGhostObjectsPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\LoginThrottlingPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadDirectoryParameterPass;
use CleverAge\UiProcessBundle\DependencyInjection\Compiler\UploadedFileSubscriberPass;
use CleverAge\UiProcessBundle\DependencyInjection\Configuration;
use CleverAge\UiProcessBundle\Entity\LogRecord;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Entity\User;
use CleverAge\UiProcessBundle\Form\LaunchFormOptionsResolver;
use CleverAge\UiProcessBundle\Form\Type\LaunchType;
use CleverAge\UiProcessBundle\Form\Type\ProcessContextType;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use CleverAge\UiProcessBundle\Manager\ProcessExecutionManager;
use CleverAge\UiProcessBundle\Monolog\Handler\DoctrineProcessHandler;
use CleverAge\UiProcessBundle\Monolog\Handler\ProcessHandler;
use CleverAge\UiProcessBundle\Notifier\NotificationTrigger;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use CleverAge\UiProcessBundle\Routing\DeprecatedEasyAdminRouteAliasLoader;
use Monolog\Level;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Uid\Uuid;

#[CoversClass(PurgeLogsCommand::class)]
#[UsesClass(CleverAgeUiProcessBundle::class)]
#[UsesClass(CheckDoctrineLazyGhostObjectsPass::class)]
#[UsesClass(LaunchFormOptionsResolver::class)]
#[UsesClass(ProcessContextType::class)]
#[UsesClass(LaunchType::class)]
#[UsesClass(LaunchFormsCacheWarmer::class)]
#[UsesClass(UploadDirectoryParameterPass::class)]
#[UsesClass(LoginThrottlingPass::class)]
#[UsesClass(UploadedFileSubscriberPass::class)]
#[UsesClass(CleverAgeUiProcessExtension::class)]
#[UsesClass(DeprecatedEasyAdminRouteAliasLoader::class)]
#[UsesClass(Configuration::class)]
#[UsesClass(LogRecord::class)]
#[UsesClass(ProcessExecution::class)]
#[UsesClass(User::class)]
#[UsesClass(ProcessConfigurationsManager::class)]
#[UsesClass(NotificationTrigger::class)]
#[UsesClass(ProcessExecutionManager::class)]
#[UsesClass(DoctrineProcessHandler::class)]
#[UsesClass(ProcessHandler::class)]
#[UsesClass(ProcessExecutionRepository::class)]
class PurgeLogsCommandTest extends FunctionalTestCase
{
    /** @var list<string> */
    private array $createdFiles = [];

    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->createdFiles);
        $this->createdFiles = [];
        parent::tearDown();
    }

    public function testService(): void
    {
        $command = static::getContainer()->get('cleverage_ui_process.command.purge_logs');

        self::assertInstanceOf(PurgeLogsCommand::class, $command);
        self::assertSame('cleverage:ui-process:purge-logs', $command->getName());
        self::assertFalse($command->getDefinition()->getOption('dry-run')->acceptValue());
    }

    public function testNothingToPurgeWithoutRetention(): void
    {
        $execution = $this->createExecution('test.process');
        $this->createLogRecord($execution, 400);
        $file = $this->createLogFile('test.process', 400);

        // No process with a "logs" option
        $tester = $this->execute(null, null, [], new ProcessConfigurationsManager(new ProcessConfigurationRegistry([], 'stop')));

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('Nothing to purge', $tester->getDisplay());
        self::assertSame(1, $this->countLogRecords());
        self::assertFileExists($file);
    }

    public function testPurge(): void
    {
        // Bundle retention: 30 days in database, 60 days for the files
        $default = $this->createExecution('test.process');
        $this->createLogRecord($default, 40);
        $this->createLogRecord($default, 1);
        $defaultOldFile = $this->createLogFile('test.process', 70);
        $defaultRecentFile = $this->createLogFile('test.process', 1);
        // Not written by the bundle: kept
        $otherFile = $this->createLogFile('test.process', 70, 'notes.log');

        // Retention of the process: 90 days in database, 365 days for the files
        $overridden = $this->createExecution('test.logs_retention');
        $this->createLogRecord($overridden, 100);
        $this->createLogRecord($overridden, 40);
        $overriddenOldFile = $this->createLogFile('test.logs_retention', 400);
        $overriddenRecentFile = $this->createLogFile('test.logs_retention', 70);

        // Process no longer defined: bundle retention
        $undefined = $this->createExecution('test.removed');
        $this->createLogRecord($undefined, 40);
        $undefinedOldFile = $this->createLogFile('test.removed', 70);

        $tester = $this->execute(30, 60);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('3 log record(s) and 3 log file(s) deleted.', $tester->getDisplay());
        self::assertSame(2, $this->countLogRecords());
        self::assertSame(1, $this->countLogRecords($default));
        self::assertSame(1, $this->countLogRecords($overridden));
        self::assertSame(0, $this->countLogRecords($undefined));
        self::assertFileDoesNotExist($defaultOldFile);
        self::assertFileExists($defaultRecentFile);
        self::assertFileExists($otherFile);
        self::assertFileDoesNotExist($overriddenOldFile);
        self::assertFileExists($overriddenRecentFile);
        self::assertFileDoesNotExist($undefinedOldFile);
        // The process executions are kept
        self::assertSame(3, $this->getEntityManager()->getRepository(ProcessExecution::class)->count([]));
    }

    public function testInvalidProcessConfigurationIsPurgedWithTheBundleRetention(): void
    {
        $valid = $this->createExecution('test.valid');
        $this->createLogRecord($valid, 40);
        $validFile = $this->createLogFile('test.valid', 70);
        $invalid = $this->createExecution('test.invalid');
        $this->createLogRecord($invalid, 40);
        $this->createLogRecord($invalid, 1);
        $invalidFile = $this->createLogFile('test.invalid', 70);
        // "test.invalid" refers to an unknown task: its configuration cannot be resolved
        $registry = new ProcessConfigurationRegistry([
            'test.valid' => $this->rawProcess(['logs' => ['database_max_days' => 90]]),
            'test.invalid' => $this->rawProcess(['logs' => ['database_max_days' => 90]], ['missing']),
        ], 'stop');

        $tester = $this->execute(30, 60, [], new ProcessConfigurationsManager($registry));

        $tester->assertCommandIsSuccessful();
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('The configuration of the process "test.invalid" is not valid, its logs are purged with the retention of the bundle:', $display);
        // Retention of the process for the valid one, of the bundle for the invalid one
        self::assertSame(1, $this->countLogRecords($valid));
        self::assertSame(1, $this->countLogRecords($invalid));
        self::assertFileDoesNotExist($validFile);
        self::assertFileDoesNotExist($invalidFile);
    }

    /**
     * An invalid "logs" option is purged with the retention of the bundle, an invalid "ui" option does not affect the
     * "logs" option of its process.
     */
    public function testInvalidOptions(): void
    {
        $invalidUi = $this->createExecution('test.invalid_ui');
        $this->createLogRecord($invalidUi, 40);
        $this->createLogRecord($invalidUi, 1);
        $invalidLogs = $this->createExecution('test.invalid_logs');
        $this->createLogRecord($invalidLogs, 40);
        $this->createLogRecord($invalidLogs, 1);
        $registry = new ProcessConfigurationRegistry([
            'test.invalid_ui' => $this->rawProcess(['ui' => ['ui_launch_mode' => 'unknown'], 'logs' => ['database_max_days' => 10]]),
            'test.invalid_logs' => $this->rawProcess(['logs' => ['database_max_days' => 0]]),
        ], 'stop');

        $tester = $this->execute(30, null, [], new ProcessConfigurationsManager($registry));

        $tester->assertCommandIsSuccessful();
        $display = (string) preg_replace('/\s+/', ' ', $tester->getDisplay());
        self::assertStringContainsString('The configuration of the process "test.invalid_logs" is not valid, its logs are purged with the retention of the bundle:', $display);
        self::assertStringNotContainsString('"test.invalid_ui" is not valid', $display);
        // Retention of the process (10 days) despite its invalid "ui" option, of the bundle (30 days) for the invalid "logs" option
        self::assertSame(1, $this->countLogRecords($invalidUi));
        self::assertSame(1, $this->countLogRecords($invalidLogs));
    }

    /**
     * Before cleverage/process-bundle 5.6, an invalid process makes the listing fail.
     */
    public function testProcessListingFailureFallsBackOnTheRecordedExecutions(): void
    {
        $execution = $this->createExecution('test.invalid');
        $this->createLogRecord($execution, 40);
        $registry = $this->createStub(ProcessConfigurationRegistryInterface::class);
        $registry->method('getProcessConfigurations')->willThrowException(MissingTaskConfigurationException::create('missing'));
        $registry->method('hasProcessConfiguration')->willReturn(true);
        $registry->method('getProcessConfiguration')->willThrowException(MissingTaskConfigurationException::create('missing'));

        $tester = $this->execute(30, null, [], new ProcessConfigurationsManager($registry));

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('"test.invalid" is not valid', $tester->getDisplay());
        self::assertSame(0, $this->countLogRecords($execution));
    }

    public function testDryRun(): void
    {
        $execution = $this->createExecution('test.process');
        $this->createLogRecord($execution, 40);
        $file = $this->createLogFile('test.process', 70);

        $tester = $this->execute(30, 60, ['--dry-run' => true]);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('1 log record(s) and 1 log file(s) to delete (dry run).', $tester->getDisplay());
        self::assertSame(1, $this->countLogRecords());
        self::assertFileExists($file);
    }

    public function testRetentionOfTheProcessOnly(): void
    {
        // No bundle retention: only the process with a "logs" option is purged
        $default = $this->createExecution('test.process');
        $this->createLogRecord($default, 400);
        $defaultFile = $this->createLogFile('test.process', 400);
        $overridden = $this->createExecution('test.logs_retention');
        $this->createLogRecord($overridden, 100);
        $overriddenFile = $this->createLogFile('test.logs_retention', 400);

        $tester = $this->execute(null, null);

        $tester->assertCommandIsSuccessful();
        self::assertSame(1, $this->countLogRecords($default));
        self::assertSame(0, $this->countLogRecords($overridden));
        self::assertFileExists($defaultFile);
        self::assertFileDoesNotExist($overriddenFile);
        self::assertStringNotContainsString('test.process ', $tester->getDisplay());
    }

    public function testDatabaseRetentionOnly(): void
    {
        $execution = $this->createExecution('test.process');
        $this->createLogRecord($execution, 40);
        $file = $this->createLogFile('test.process', 400);

        $tester = $this->execute(30, null);

        $tester->assertCommandIsSuccessful();
        self::assertSame(0, $this->countLogRecords());
        self::assertFileExists($file);
    }

    public function testRecordsAreDeletedByBatches(): void
    {
        $execution = $this->createExecution('test.process');
        $connection = $this->getEntityManager()->getConnection();
        $connection->beginTransaction();
        for ($i = 0; $i < 2001; ++$i) {
            $connection->insert('log_record', [
                'channel' => 'cleverage_process',
                'level' => Level::Info->value,
                'message' => 'Message '.$i,
                'context' => '[]',
                'created_at' => (new \DateTimeImmutable('-40 days'))->format('Y-m-d H:i:s'),
                'process_execution_id' => $execution->getId(),
            ]);
        }
        $connection->commit();

        $tester = $this->execute(30, null);

        $tester->assertCommandIsSuccessful();
        self::assertStringContainsString('2001 log record(s) and 0 log file(s) deleted.', $tester->getDisplay());
        self::assertSame(0, $this->countLogRecords());
    }

    /**
     * @param array<string, mixed> $input
     */
    private function execute(
        ?int $databaseMaxDays,
        ?int $fileMaxDays,
        array $input = [],
        ?ProcessConfigurationsManager $processConfigurationsManager = null,
    ): CommandTester {
        $processConfigurationsManager ??= $this->getProcessConfigurationsManager();
        /** @var string $logDirectory */
        $logDirectory = static::getContainer()->getParameter('kernel.logs_dir');
        $command = new PurgeLogsCommand(
            $this->getEntityManager(),
            $processConfigurationsManager,
            $logDirectory,
            $databaseMaxDays,
            $fileMaxDays
        );
        $tester = new CommandTester($command);
        $tester->execute($input);

        return $tester;
    }

    /**
     * Raw configuration of a process with a single task, as the process bundle configuration would give it.
     *
     * @param array<string, mixed> $options
     * @param list<string>         $outputs
     *
     * @return array<string, mixed>
     */
    private function rawProcess(array $options = [], array $outputs = []): array
    {
        return [
            'options' => $options,
            'entry_point' => null,
            'end_point' => null,
            'description' => '',
            'help' => '',
            'public' => true,
            'tasks' => [
                'data' => [
                    'service' => '@CleverAge\ProcessBundle\Task\DummyTask',
                    'options' => [],
                    'description' => '',
                    'help' => '',
                    'outputs' => $outputs,
                    'errors' => [],
                    'error_outputs' => [],
                    'error_strategy' => null,
                    'log_level' => null,
                ],
            ],
        ];
    }

    private function getProcessConfigurationsManager(): ProcessConfigurationsManager
    {
        /** @var ProcessConfigurationsManager $processConfigurationsManager */
        $processConfigurationsManager = static::getContainer()->get('cleverage_ui_process.manager.process_configuration');

        return $processConfigurationsManager;
    }

    private function createExecution(string $code): ProcessExecution
    {
        $execution = new ProcessExecution($code, Uuid::v4().'.log');
        $execution->end();
        $this->getEntityManager()->persist($execution);
        $this->getEntityManager()->flush();

        return $execution;
    }

    private function createLogRecord(ProcessExecution $execution, int $daysAgo): void
    {
        $this->getEntityManager()->persist(new LogRecord(
            new \Monolog\LogRecord(new \DateTimeImmutable(\sprintf('-%d days', $daysAgo)), 'cleverage_process', Level::Info, 'Message'),
            $execution
        ));
        $this->getEntityManager()->flush();
    }

    private function createLogFile(string $code, int $daysAgo, ?string $filename = null): string
    {
        /** @var string $logDirectory */
        $logDirectory = static::getContainer()->getParameter('kernel.logs_dir');
        $path = \sprintf('%s/%s/%s', $logDirectory, $code, $filename ?? Uuid::v4().'.log');
        (new Filesystem())->dumpFile($path, 'log');
        touch($path, (new \DateTimeImmutable(\sprintf('-%d days', $daysAgo)))->getTimestamp());
        $this->createdFiles[] = $path;

        return $path;
    }

    private function countLogRecords(?ProcessExecution $execution = null): int
    {
        $criteria = $execution instanceof ProcessExecution ? ['processExecution' => $execution] : [];

        return $this->getEntityManager()->getRepository(LogRecord::class)->count($criteria);
    }
}
