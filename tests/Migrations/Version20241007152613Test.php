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

use CleverAge\UiProcessBundle\Migrations\Version20241007152613;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Version20241007152613::class)]
class Version20241007152613Test extends MigrationTestCase
{
    public function testDescription(): void
    {
        self::assertSame('Add process_execution.context', $this->createMigration(Version20241007152613::class)->getDescription());
    }

    #[DataProvider('providePlatforms')]
    public function testUpAddsTheColumn(AbstractPlatform $platform): void
    {
        $migration = $this->createMigration(Version20241007152613::class, $platform);
        $migration->up($this->createSchema(['process_execution' => []]));

        self::assertSame(['ALTER TABLE process_execution ADD context JSON NOT NULL'], $this->getStatements($migration));
    }

    public function testUpDoesNothingWhenTheColumnExists(): void
    {
        $migration = $this->createMigration(Version20241007152613::class);
        $migration->up($this->createSchema(['process_execution' => ['context']]));

        self::assertSame([], $this->getStatements($migration));
    }

    public function testUpDoesNothingWithoutTheTable(): void
    {
        $migration = $this->createMigration(Version20241007152613::class);
        $migration->up($this->createSchema([]));

        self::assertSame([], $this->getStatements($migration));
    }

    #[DataProvider('providePlatforms')]
    public function testDownDropsTheColumn(AbstractPlatform $platform): void
    {
        $migration = $this->createMigration(Version20241007152613::class, $platform);
        $migration->down($this->createSchema(['process_execution' => ['context']]));

        self::assertSame(['ALTER TABLE process_execution DROP context'], $this->getStatements($migration));
    }

    public function testDownDoesNothingWithoutTheColumn(): void
    {
        $migration = $this->createMigration(Version20241007152613::class);
        $migration->down($this->createSchema(['process_execution' => []]));

        self::assertSame([], $this->getStatements($migration));
    }

    public function testDownDoesNothingWithoutTheTable(): void
    {
        $migration = $this->createMigration(Version20241007152613::class);
        $migration->down($this->createSchema([]));

        self::assertSame([], $this->getStatements($migration));
    }
}
