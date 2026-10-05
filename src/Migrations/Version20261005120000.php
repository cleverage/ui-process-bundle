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
 * Align on the entity mapping log_record.process_execution_id, created as nullable by Version20231006111525, and
 * process_execution.context, created as NOT NULL by Version20241007152613.
 */
final class Version20261005120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Make log_record.process_execution_id NOT NULL, process_execution.context nullable';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $mySql = $platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform;
        if (!$mySql && !$platform instanceof PostgreSQLPlatform) {
            return;
        }

        // Log records without process execution are never displayed (the UI lists the logs of an execution)
        $this->addSql('DELETE FROM log_record WHERE process_execution_id IS NULL');
        $this->addSql($mySql
            ? 'ALTER TABLE log_record MODIFY process_execution_id INT NOT NULL'
            : 'ALTER TABLE log_record ALTER process_execution_id SET NOT NULL');
        $this->addSql($mySql
            ? 'ALTER TABLE process_execution MODIFY context JSON DEFAULT NULL'
            : 'ALTER TABLE process_execution ALTER context DROP NOT NULL');
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        $mySql = $platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform;
        if (!$mySql && !$platform instanceof PostgreSQLPlatform) {
            return;
        }

        $this->addSql($mySql
            ? 'ALTER TABLE log_record MODIFY process_execution_id INT DEFAULT NULL'
            : 'ALTER TABLE log_record ALTER process_execution_id DROP NOT NULL');
        $this->addSql($mySql
            ? 'ALTER TABLE process_execution MODIFY context JSON NOT NULL'
            : 'ALTER TABLE process_execution ALTER context SET NOT NULL');
    }
}
