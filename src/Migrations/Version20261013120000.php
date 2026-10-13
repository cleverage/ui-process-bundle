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

namespace CleverAge\UiProcessBundle\Migrations;

use Doctrine\DBAL\Platforms\MariaDBPlatform;
use Doctrine\DBAL\Platforms\MySQLPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add process_schedule.enabled: the existing schedules are enabled.
 */
final class Version20261013120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add process_schedule.enabled';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform) {
            $this->addSql('ALTER TABLE process_schedule ADD enabled TINYINT(1) DEFAULT 1 NOT NULL');
        }

        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE process_schedule ADD enabled BOOLEAN DEFAULT true NOT NULL');
        }
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform || $platform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE process_schedule DROP enabled');
        }
    }
}
