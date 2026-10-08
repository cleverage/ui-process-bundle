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
 * Remove the (DC2Type:...) column comments created by Version20231006111525 and Version20240729151928 when the type
 * comments are not used (always with DBAL 4, disable_type_comments with DBAL 3): the schema was always reported as out
 * of sync. With DBAL 3 using the type comments, they are kept.
 */
final class Version20261008120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Remove the (DC2Type) column comments when the type comments are not used';
    }

    public function up(Schema $schema): void
    {
        if (!$this->connection->getConfiguration()->getDisableTypeComments()) {
            return;
        }

        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform) {
            $this->addSql('ALTER TABLE log_record MODIFY context JSON NOT NULL, MODIFY created_at DATETIME NOT NULL');
            $this->addSql('ALTER TABLE process_execution MODIFY start_date DATETIME NOT NULL, MODIFY end_date DATETIME DEFAULT NULL, MODIFY report JSON NOT NULL');
            $this->addSql('ALTER TABLE process_schedule MODIFY context JSON NOT NULL');
            $this->addSql('ALTER TABLE process_user MODIFY roles JSON NOT NULL');
        }

        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('COMMENT ON COLUMN log_record.created_at IS NULL');
            $this->addSql('COMMENT ON COLUMN process_execution.start_date IS NULL');
            $this->addSql('COMMENT ON COLUMN process_execution.end_date IS NULL');
        }
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        if (!$this->connection->getConfiguration()->getDisableTypeComments()) {
            return;
        }

        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform) {
            $this->addSql('ALTER TABLE log_record MODIFY context JSON NOT NULL COMMENT \'(DC2Type:json)\', MODIFY created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\'');
            $this->addSql('ALTER TABLE process_execution MODIFY start_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', MODIFY end_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', MODIFY report JSON NOT NULL COMMENT \'(DC2Type:json)\'');
            $this->addSql('ALTER TABLE process_schedule MODIFY context JSON NOT NULL COMMENT \'(DC2Type:json)\'');
            $this->addSql('ALTER TABLE process_user MODIFY roles JSON NOT NULL COMMENT \'(DC2Type:json)\'');
        }

        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('COMMENT ON COLUMN log_record.created_at IS \'(DC2Type:datetime_immutable)\'');
            $this->addSql('COMMENT ON COLUMN process_execution.start_date IS \'(DC2Type:datetime_immutable)\'');
            $this->addSql('COMMENT ON COLUMN process_execution.end_date IS \'(DC2Type:datetime_immutable)\'');
        }
    }
}
