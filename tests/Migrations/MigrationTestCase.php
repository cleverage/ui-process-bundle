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

namespace CleverAge\UiProcessBundle\Tests\Migrations;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\Migrations\Query\Query;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Migrations are only instantiated with a stubbed connection: their SQL statements are collected, never executed.
 */
abstract class MigrationTestCase extends TestCase
{
    /**
     * @return iterable<string, array{AbstractPlatform}>
     */
    public static function provideMySqlPlatforms(): iterable
    {
        yield 'MySQL' => [new MySQLPlatform()];
        yield 'MariaDB' => [new MariaDBPlatform()];
    }

    /**
     * @return iterable<string, array{AbstractPlatform}>
     */
    public static function providePlatforms(): iterable
    {
        yield from self::provideMySqlPlatforms();
        yield 'PostgreSQL' => [new PostgreSQLPlatform()];
        yield 'SQLite' => [new SQLitePlatform()];
    }

    /**
     * @param class-string<AbstractMigration> $class
     */
    protected function createMigration(string $class, ?AbstractPlatform $platform = null): AbstractMigration
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('getDatabasePlatform')->willReturn($platform ?? new MySQLPlatform());

        return new $class($connection, new NullLogger());
    }

    /**
     * @return list<string>
     */
    protected function getStatements(AbstractMigration $migration): array
    {
        return array_values(array_map(static fn (Query $query): string => $query->getStatement(), $migration->getSql()));
    }

    /**
     * @param array<string, list<string>> $tables table name => column names
     */
    protected function createSchema(array $tables): Schema
    {
        $schema = new Schema();
        foreach ($tables as $name => $columns) {
            $table = $schema->createTable($name);
            $table->addColumn('id', 'integer');
            foreach ($columns as $column) {
                $table->addColumn($column, 'string');
            }
        }

        return $schema;
    }
}
