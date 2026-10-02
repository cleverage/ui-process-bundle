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

use CleverAge\UiProcessBundle\Migrations\Version20241007134542;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Version20241007134542::class)]
class Version20241007134542Test extends MigrationTestCase
{
    public function testDescription(): void
    {
        self::assertSame('Add process_user.timezone', $this->createMigration(Version20241007134542::class)->getDescription());
    }

    #[DataProvider('providePlatforms')]
    public function testUpAddsTheColumn(AbstractPlatform $platform): void
    {
        $migration = $this->createMigration(Version20241007134542::class, $platform);
        $migration->up($this->createSchema(['process_user' => []]));

        self::assertSame(['ALTER TABLE process_user ADD timezone VARCHAR(255) DEFAULT NULL'], $this->getStatements($migration));
    }

    public function testUpDoesNothingWhenTheColumnExists(): void
    {
        $migration = $this->createMigration(Version20241007134542::class);
        $migration->up($this->createSchema(['process_user' => ['timezone']]));

        self::assertSame([], $this->getStatements($migration));
    }

    public function testUpDoesNothingWithoutTheTable(): void
    {
        $migration = $this->createMigration(Version20241007134542::class);
        $migration->up($this->createSchema([]));

        self::assertSame([], $this->getStatements($migration));
    }

    #[DataProvider('providePlatforms')]
    public function testDownDropsTheColumn(AbstractPlatform $platform): void
    {
        $migration = $this->createMigration(Version20241007134542::class, $platform);
        $migration->down($this->createSchema(['process_user' => ['timezone']]));

        self::assertSame(['ALTER TABLE process_user DROP timezone'], $this->getStatements($migration));
    }

    public function testDownDoesNothingWithoutTheColumn(): void
    {
        $migration = $this->createMigration(Version20241007134542::class);
        $migration->down($this->createSchema(['process_user' => []]));

        self::assertSame([], $this->getStatements($migration));
    }

    public function testDownDoesNothingWithoutTheTable(): void
    {
        $migration = $this->createMigration(Version20241007134542::class);
        $migration->down($this->createSchema([]));

        self::assertSame([], $this->getStatements($migration));
    }
}
