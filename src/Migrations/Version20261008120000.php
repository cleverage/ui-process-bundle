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
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Remove the (DC2Type:...) column comments created by Version20231006111525 and Version20240729151928, no longer used
 * since DBAL 4: the schema was always reported as out of sync.
 */
final class Version20261008120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Remove the (DC2Type) column comments';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (!$platform instanceof MariaDBPlatform && !$platform instanceof MySQLPlatform) {
            return;
        }

        $this->addSql('ALTER TABLE log_record MODIFY context JSON NOT NULL, MODIFY created_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE process_execution MODIFY start_date DATETIME NOT NULL, MODIFY end_date DATETIME DEFAULT NULL, MODIFY report JSON NOT NULL');
        $this->addSql('ALTER TABLE process_schedule MODIFY context JSON NOT NULL');
        $this->addSql('ALTER TABLE process_user MODIFY roles JSON NOT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if (!$platform instanceof MariaDBPlatform && !$platform instanceof MySQLPlatform) {
            return;
        }

        $this->addSql('ALTER TABLE log_record MODIFY context JSON NOT NULL COMMENT \'(DC2Type:json)\', MODIFY created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
        $this->addSql('ALTER TABLE process_execution MODIFY start_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', MODIFY end_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', MODIFY report JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE process_schedule MODIFY context JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        $this->addSql('ALTER TABLE process_user MODIFY roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
    }
}
