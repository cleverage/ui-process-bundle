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

namespace CleverAge\UiProcessBundle\Command;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Finder\Finder;

/**
 * Deletes the log records (log_record table) older than "logs.database_max_days" days and the log files older than
 * "logs.file_max_days" days, both overridable by the "logs" option of each process. The process executions are kept.
 *
 * DBAL queries are used: the log records are deleted by batches, without loading them.
 *
 * @internal
 */
#[AsCommand(
    name: 'cleverage:ui-process:purge-logs',
    description: 'Delete the log records and the log files of the process executions older than the configured retention.'
)]
class PurgeLogsCommand extends Command
{
    private const BATCH_SIZE = 1000;

    /**
     * Name of the log files written by the ProcessHandler: <uuid>.log.
     */
    private const LOG_FILE_PATTERN = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\.log$/';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ProcessConfigurationsManager $processConfigurationsManager,
        private readonly string $logDirectory,
        private readonly ?int $databaseMaxDays = null,
        private readonly ?int $fileMaxDays = null,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Count the log records and the log files to delete, without deleting them');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $style = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $connection = $this->entityManager->getConnection();

        $rows = [];
        $totalRecords = 0;
        $totalFiles = 0;
        foreach ($this->getProcessCodes($connection) as $code) {
            $options = $this->processConfigurationsManager->getLogsOptions($code);
            $databaseMaxDays = $options['database_max_days'] ?? $this->databaseMaxDays;
            $fileMaxDays = $options['file_max_days'] ?? $this->fileMaxDays;
            if (null === $databaseMaxDays && null === $fileMaxDays) {
                continue;
            }

            $records = null === $databaseMaxDays ? null : $this->purgeLogRecords($connection, $code, $databaseMaxDays, $dryRun);
            $files = null === $fileMaxDays ? null : $this->purgeLogFiles($code, $fileMaxDays, $dryRun);
            $totalRecords += $records ?? 0;
            $totalFiles += $files ?? 0;
            $rows[] = [$code, $databaseMaxDays ?? '-', $records ?? '-', $fileMaxDays ?? '-', $files ?? '-'];
        }

        if ([] === $rows) {
            $style->success('Nothing to purge: no retention configured ("logs.database_max_days", "logs.file_max_days").');

            return Command::SUCCESS;
        }

        $style->table(
            ['Process', 'Database max days', $dryRun ? 'Log records to delete' : 'Log records deleted', 'File max days', $dryRun ? 'Log files to delete' : 'Log files deleted'],
            $rows
        );
        $style->success(\sprintf(
            $dryRun ? '%d log record(s) and %d log file(s) to delete (dry run).' : '%d log record(s) and %d log file(s) deleted.',
            $totalRecords,
            $totalFiles
        ));

        return Command::SUCCESS;
    }

    /**
     * Codes of the recorded process executions and of the defined processes, sorted.
     *
     * @return list<string>
     */
    private function getProcessCodes(Connection $connection): array
    {
        /** @var list<string> $codes */
        $codes = $connection->fetchFirstColumn('SELECT DISTINCT code FROM process_execution');
        $configurations = [...$this->processConfigurationsManager->getPublicProcesses(), ...$this->processConfigurationsManager->getPrivateProcesses()];
        $codes = array_unique([
            ...$codes,
            ...array_map(static fn (ProcessConfiguration $configuration): string => $configuration->getCode(), array_values($configurations)),
        ]);
        sort($codes);

        return $codes;
    }

    private function purgeLogRecords(Connection $connection, string $code, int $maxDays, bool $dryRun): int
    {
        $limit = new \DateTimeImmutable(\sprintf('-%d days', $maxDays));
        $queryBuilder = $connection->createQueryBuilder()
            ->from('log_record', 'lr')
            ->innerJoin('lr', 'process_execution', 'pe', 'pe.id = lr.process_execution_id')
            ->where('pe.code = :code')
            ->andWhere('lr.created_at < :limit')
            ->setParameter('code', $code)
            ->setParameter('limit', $limit, Types::DATETIME_IMMUTABLE);

        if ($dryRun) {
            /** @var int|string|false $count */
            $count = $queryBuilder->select('COUNT(lr.id)')->fetchOne();

            return (int) $count;
        }

        $queryBuilder->select('lr.id')->setMaxResults(self::BATCH_SIZE);
        $deleted = 0;
        do {
            $ids = $queryBuilder->fetchFirstColumn();
            if ([] !== $ids) {
                $deleted += (int) $connection->executeStatement(
                    'DELETE FROM log_record WHERE id IN (?)',
                    [$ids],
                    [ArrayParameterType::INTEGER]
                );
            }
        } while (self::BATCH_SIZE === \count($ids));

        return $deleted;
    }

    private function purgeLogFiles(string $code, int $maxDays, bool $dryRun): int
    {
        $directory = $this->logDirectory.\DIRECTORY_SEPARATOR.$code;
        // The code is the name of the directory of the log files: never look outside the log directory
        if (\in_array($code, ['', '.', '..'], true) || str_contains($code, '/') || str_contains($code, '\\') || !is_dir($directory)) {
            return 0;
        }

        $limit = (new \DateTimeImmutable(\sprintf('-%d days', $maxDays)))->getTimestamp();
        $finder = (new Finder())->files()->in($directory)->depth(0)->name(self::LOG_FILE_PATTERN)
            ->filter(static fn (\SplFileInfo $file): bool => $file->getMTime() < $limit);
        $files = array_keys(iterator_to_array($finder));
        if (!$dryRun) {
            (new Filesystem())->remove($files);
        }

        return \count($files);
    }
}
