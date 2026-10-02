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

use CleverAge\UiProcessBundle\Migrations\Version20240730090403;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;

#[CoversClass(Version20240730090403::class)]
class Version20240730090403Test extends MigrationTestCase
{
    public function testDescription(): void
    {
        self::assertSame('Add process_user.token', $this->createMigration(Version20240730090403::class)->getDescription());
    }

    #[DataProvider('providePlatforms')]
    public function testUp(AbstractPlatform $platform): void
    {
        $migration = $this->createMigration(Version20240730090403::class, $platform);
        $migration->up($this->createSchema(['process_user' => []]));

        self::assertSame(['ALTER TABLE process_user ADD token VARCHAR(255) DEFAULT NULL'], $this->getStatements($migration));
    }

    #[DataProvider('providePlatforms')]
    public function testDown(AbstractPlatform $platform): void
    {
        $migration = $this->createMigration(Version20240730090403::class, $platform);
        $migration->down($this->createSchema(['process_user' => ['token']]));

        self::assertSame(['ALTER TABLE process_user DROP token'], $this->getStatements($migration));
    }
}
