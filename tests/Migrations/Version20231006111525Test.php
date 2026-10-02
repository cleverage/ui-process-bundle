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

use CleverAge\UiProcessBundle\Migrations\Version20231006111525;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Version20231006111525::class)]
class Version20231006111525Test extends MigrationTestCase
{
    private const FOREIGN_KEY = 'ALTER TABLE log_record ADD CONSTRAINT FK_8ECECC333DAC0075 FOREIGN KEY (process_execution_id) REFERENCES process_execution (id) ON DELETE CASCADE';

    public function testDescription(): void
    {
        self::assertSame(
            'Create tables log_record, process_execution and process_user',
            $this->createMigration(Version20231006111525::class)->getDescription()
        );
    }

    #[DataProvider('provideMySqlPlatforms')]
    public function testUpOnMySql(AbstractPlatform $platform): void
    {
        $migration = $this->createMigration(Version20231006111525::class, $platform);
        $migration->up($this->createSchema([]));

        $statements = $this->getStatements($migration);
        self::assertCount(4, $statements);
        self::assertStringStartsWith('CREATE TABLE log_record (id INT AUTO_INCREMENT NOT NULL, process_execution_id INT DEFAULT NULL,', $statements[0]);
        self::assertStringStartsWith('CREATE TABLE process_execution (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(255) NOT NULL,', $statements[1]);
        self::assertSame(self::FOREIGN_KEY, $statements[2]);
        self::assertStringStartsWith('CREATE TABLE process_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL,', $statements[3]);
        self::assertStringContainsString('UNIQUE INDEX UNIQ_627A047CE7927C74 (email)', $statements[3]);
        foreach ([0, 1, 3] as $index) {
            self::assertStringEndsWith('DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB', $statements[$index]);
        }
    }

    public function testUpOnPostgreSql(): void
    {
        $migration = $this->createMigration(Version20231006111525::class, new PostgreSQLPlatform());
        $migration->up($this->createSchema([]));

        $statements = $this->getStatements($migration);
        self::assertCount(17, $statements);
        self::assertSame('CREATE SEQUENCE log_record_id_seq INCREMENT BY 1 MINVALUE 1 START 1', $statements[0]);
        self::assertStringStartsWith('CREATE TABLE log_record (id INT NOT NULL,', $statements[1]);
        self::assertSame('CREATE SEQUENCE process_execution_id_seq INCREMENT BY 1 MINVALUE 1 START 1', $statements[6]);
        self::assertStringStartsWith('CREATE TABLE process_execution (id INT NOT NULL,', $statements[7]);
        self::assertSame(self::FOREIGN_KEY.' NOT DEFERRABLE INITIALLY IMMEDIATE', $statements[12]);
        self::assertSame('CREATE SEQUENCE process_user_id_seq INCREMENT BY 1 MINVALUE 1 START 1', $statements[13]);
        self::assertStringStartsWith('CREATE TABLE process_user (id INT NOT NULL,', $statements[14]);
        self::assertSame('CREATE UNIQUE INDEX UNIQ_627A047CE7927C74 ON process_user (email)', $statements[15]);
        self::assertSame('CREATE INDEX idx_process_user_email ON process_user (email)', $statements[16]);
        foreach ($statements as $statement) {
            self::assertStringNotContainsString('AUTO_INCREMENT', $statement);
            self::assertStringNotContainsString('ENGINE', $statement);
        }
    }

    #[DataProvider('providePlatforms')]
    public function testUpDoesNothingWhenTheTablesExist(AbstractPlatform $platform): void
    {
        $migration = $this->createMigration(Version20231006111525::class, $platform);
        $migration->up($this->createSchema(['log_record' => [], 'process_execution' => [], 'process_user' => []]));

        self::assertSame([], $this->getStatements($migration));
    }

    #[DataProvider('provideMySqlPlatforms')]
    public function testUpOnlyCreatesMissingTablesOnMySql(AbstractPlatform $platform): void
    {
        $migration = $this->createMigration(Version20231006111525::class, $platform);
        $migration->up($this->createSchema(['log_record' => [], 'process_execution' => []]));

        $statements = $this->getStatements($migration);
        self::assertCount(1, $statements);
        self::assertStringStartsWith('CREATE TABLE process_user (', $statements[0]);
    }

    public function testUpOnlyCreatesMissingTablesOnPostgreSql(): void
    {
        $migration = $this->createMigration(Version20231006111525::class, new PostgreSQLPlatform());
        $migration->up($this->createSchema(['log_record' => [], 'process_user' => []]));

        $statements = $this->getStatements($migration);
        self::assertCount(7, $statements);
        self::assertSame('CREATE SEQUENCE process_execution_id_seq INCREMENT BY 1 MINVALUE 1 START 1', $statements[0]);
        self::assertSame(self::FOREIGN_KEY.' NOT DEFERRABLE INITIALLY IMMEDIATE', $statements[6]);
    }

    public function testUpDoesNothingOnOtherPlatforms(): void
    {
        $migration = $this->createMigration(Version20231006111525::class, new SQLitePlatform());
        $migration->up($this->createSchema([]));

        self::assertSame([], $this->getStatements($migration));
    }

    #[DataProvider('providePlatforms')]
    public function testDown(AbstractPlatform $platform): void
    {
        $migration = $this->createMigration(Version20231006111525::class, $platform);
        $migration->down($this->createSchema(['log_record' => [], 'process_execution' => [], 'process_user' => []]));

        self::assertSame(
            [
                'ALTER TABLE log_record DROP CONSTRAINT FK_8ECECC333DAC0075',
                'DROP TABLE log_record',
                'DROP TABLE process_execution',
                'DROP TABLE process_user',
            ],
            $this->getStatements($migration)
        );
    }
}
