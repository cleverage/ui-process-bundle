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

use CleverAge\UiProcessBundle\Migrations\Version20240729151928;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Version20240729151928::class)]
class Version20240729151928Test extends MigrationTestCase
{
    public function testDescription(): void
    {
        self::assertSame('Create table process_schedule', $this->createMigration(Version20240729151928::class)->getDescription());
    }

    #[DataProvider('provideMySqlPlatforms')]
    public function testUpOnMySql(AbstractPlatform $platform): void
    {
        $migration = $this->createMigration(Version20240729151928::class, $platform);
        $migration->up($this->createSchema([]));

        $statements = $this->getStatements($migration);
        self::assertCount(1, $statements);
        self::assertStringStartsWith('CREATE TABLE process_schedule (', $statements[0]);
        foreach (['process VARCHAR(255) NOT NULL', 'type VARCHAR(6) NOT NULL', 'expression VARCHAR(255) NOT NULL', 'input VARCHAR(255)', 'context JSON NOT NULL', 'ENGINE = InnoDB'] as $part) {
            self::assertStringContainsString($part, $statements[0]);
        }
    }

    public function testUpOnPostgreSql(): void
    {
        $migration = $this->createMigration(Version20240729151928::class, new PostgreSQLPlatform());
        $migration->up($this->createSchema([]));

        self::assertSame(
            ['CREATE TABLE process_schedule (id INT AUTO_INCREMENT NOT NULL, process VARCHAR(255) NOT NULL, type VARCHAR(6) NOT NULL, expression VARCHAR(255) NOT NULL, input VARCHAR(255), context JSON NOT NULL, PRIMARY KEY(id))'],
            $this->getStatements($migration)
        );
    }

    public function testUpDoesNothingOnOtherPlatforms(): void
    {
        $migration = $this->createMigration(Version20240729151928::class, new SQLitePlatform());
        $migration->up($this->createSchema([]));

        self::assertSame([], $this->getStatements($migration));
    }

    #[DataProvider('providePlatforms')]
    public function testDown(AbstractPlatform $platform): void
    {
        $migration = $this->createMigration(Version20240729151928::class, $platform);
        $migration->down($this->createSchema(['process_schedule' => []]));

        self::assertSame(['DROP TABLE process_schedule'], $this->getStatements($migration));
    }
}
