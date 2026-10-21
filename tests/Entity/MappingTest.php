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

namespace CleverAge\UiProcessBundle\Tests\Entity;

use CleverAge\UiProcessBundle\Entity\LogRecord;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Entity\ProcessSchedule;
use CleverAge\UiProcessBundle\Entity\User;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Mapping\DefaultNamingStrategy;
use Doctrine\ORM\Mapping\Driver\AttributeDriver;
use Doctrine\ORM\Mapping\NamingStrategy;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\Persistence\Mapping\RuntimeReflectionService;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The tables and columns are created by the Doctrine migrations of the bundle: the mapping must not depend on the
 * naming strategy of the application.
 */
#[CoversClass(LogRecord::class)]
#[CoversClass(ProcessExecution::class)]
#[CoversClass(ProcessSchedule::class)]
#[CoversClass(User::class)]
class MappingTest extends TestCase
{
    /**
     * @return iterable<string, array{NamingStrategy}>
     */
    public static function provideNamingStrategies(): iterable
    {
        // Doctrine default (DoctrineBundle default when "naming_strategy" is not configured)
        yield 'default' => [new DefaultNamingStrategy()];
        // Symfony recipe
        yield 'underscore_number_aware' => [self::createUnderscoreNamingStrategy(\CASE_LOWER)];
        yield 'underscore_number_aware_upper' => [self::createUnderscoreNamingStrategy(\CASE_UPPER)];
    }

    #[DataProvider('provideNamingStrategies')]
    public function testNamesDoNotDependOnTheNamingStrategy(NamingStrategy $namingStrategy): void
    {
        $tables = [
            LogRecord::class => ['log_record', [
                'id' => 'id',
                'channel' => 'channel',
                'level' => 'level',
                'message' => 'message',
                'context' => 'context',
                'createdAt' => 'created_at',
            ]],
            ProcessExecution::class => ['process_execution', [
                'id' => 'id',
                'code' => 'code',
                'logFilename' => 'log_filename',
                'startDate' => 'start_date',
                'endDate' => 'end_date',
                'status' => 'status',
                'stopRequestedAt' => 'stop_requested_at',
                'heartbeatAt' => 'heartbeat_at',
                'report' => 'report',
                'context' => 'context',
            ]],
            ProcessSchedule::class => ['process_schedule', [
                'id' => 'id',
                'process' => 'process',
                'type' => 'type',
                'expression' => 'expression',
                'input' => 'input',
                'context' => 'context',
                'enabled' => 'enabled',
            ]],
            User::class => ['process_user', [
                'id' => 'id',
                'email' => 'email',
                'firstname' => 'firstname',
                'lastname' => 'lastname',
                'roles' => 'roles',
                'password' => 'password',
                'timezone' => 'timezone',
                'locale' => 'locale',
                'token' => 'token',
            ]],
        ];

        foreach ($tables as $class => [$table, $columns]) {
            $metadata = $this->loadMetadata($class, $namingStrategy);

            self::assertSame($table, $metadata->getTableName(), $class);
            $mappedColumns = [];
            foreach ($metadata->getFieldNames() as $field) {
                $mappedColumns[$field] = $metadata->getColumnName($field);
            }
            self::assertEqualsCanonicalizing($columns, $mappedColumns, $class);

            // The indexes reference existing columns
            /** @var array<string, array{columns?: list<string>}> $indexes */
            $indexes = $metadata->table['indexes'] ?? [];
            foreach ($indexes as $name => $index) {
                foreach ($index['columns'] ?? [] as $column) {
                    self::assertContains($column, $mappedColumns + ['process_execution_id'], \sprintf('%s: index %s', $class, $name));
                }
            }
        }

        self::assertSame('process_execution_id', $this->loadMetadata(LogRecord::class, $namingStrategy)->getSingleAssociationJoinColumnName('processExecution'));
    }

    private static function createUnderscoreNamingStrategy(int $case): UnderscoreNamingStrategy
    {
        // Doctrine ORM 2: not number aware by default (deprecated); ORM 3: always number aware, single argument
        return (new \ReflectionClass(UnderscoreNamingStrategy::class))->newInstance($case, true);
    }

    /**
     * @param class-string $class
     *
     * @return ClassMetadata<object>
     */
    private function loadMetadata(string $class, NamingStrategy $namingStrategy): ClassMetadata
    {
        $metadata = new ClassMetadata($class, $namingStrategy);
        $metadata->initializeReflection(new RuntimeReflectionService());
        (new AttributeDriver([\dirname(__DIR__, 2).'/src/Entity'], true))->loadMetadataForClass($class, $metadata);

        return $metadata;
    }
}
