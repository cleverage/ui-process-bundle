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

final class Version20231006111525 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create tables log_record, process_execution and process_user';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();

        if ($platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform) {
            if (!$schema->hasTable('log_record')) {
                $this->addSql('CREATE TABLE log_record (id INT AUTO_INCREMENT NOT NULL, process_execution_id INT DEFAULT NULL, channel VARCHAR(64) NOT NULL, level INT NOT NULL, message VARCHAR(512) NOT NULL, context JSON NOT NULL COMMENT \'(DC2Type:json)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', INDEX IDX_8ECECC333DAC0075 (process_execution_id), INDEX idx_log_record_level (level), INDEX idx_log_record_created_at (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            }
            if (!$schema->hasTable('process_execution')) {
                $this->addSql('CREATE TABLE process_execution (id INT AUTO_INCREMENT NOT NULL, code VARCHAR(255) NOT NULL, log_filename VARCHAR(255) NOT NULL, start_date DATETIME NOT NULL COMMENT \'(DC2Type:datetime_immutable)\', end_date DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime_immutable)\', status VARCHAR(255) NOT NULL, report JSON NOT NULL COMMENT \'(DC2Type:json)\', INDEX idx_process_execution_code (code), INDEX idx_process_execution_start_date (start_date), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
                $this->addSql('ALTER TABLE log_record ADD CONSTRAINT FK_8ECECC333DAC0075 FOREIGN KEY (process_execution_id) REFERENCES process_execution (id) ON DELETE CASCADE');
            }
            if (!$schema->hasTable('process_user')) {
                $this->addSql('CREATE TABLE process_user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, firstname VARCHAR(255) DEFAULT NULL, lastname VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL COMMENT \'(DC2Type:json)\', password VARCHAR(255) DEFAULT NULL, UNIQUE INDEX UNIQ_627A047CE7927C74 (email), INDEX idx_process_user_email (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
            }
        }

        if ($platform instanceof PostgreSQLPlatform) {
            if (!$schema->hasTable('log_record')) {
                $this->addSql('CREATE SEQUENCE log_record_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
                $this->addSql('CREATE TABLE log_record (id INT NOT NULL, process_execution_id INT DEFAULT NULL, channel VARCHAR(64) NOT NULL, level INT NOT NULL, message VARCHAR(512) NOT NULL, context JSON NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id))');
                $this->addSql('CREATE INDEX IDX_8ECECC333DAC0075 ON log_record (process_execution_id)');
                $this->addSql('CREATE INDEX idx_log_record_level ON log_record (level)');
                $this->addSql('CREATE INDEX idx_log_record_created_at ON log_record (created_at)');
                $this->addSql('COMMENT ON COLUMN log_record.created_at IS \'(DC2Type:datetime_immutable)\'');
            }
            if (!$schema->hasTable('process_execution')) {
                $this->addSql('CREATE SEQUENCE process_execution_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
                $this->addSql('CREATE TABLE process_execution (id INT NOT NULL, code VARCHAR(255) NOT NULL, log_filename VARCHAR(255) NOT NULL, start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, status VARCHAR(255) NOT NULL, report JSON NOT NULL, PRIMARY KEY(id))');
                $this->addSql('CREATE INDEX idx_process_execution_code ON process_execution (code)');
                $this->addSql('CREATE INDEX idx_process_execution_start_date ON process_execution (start_date)');
                $this->addSql('COMMENT ON COLUMN process_execution.start_date IS \'(DC2Type:datetime_immutable)\'');
                $this->addSql('COMMENT ON COLUMN process_execution.end_date IS \'(DC2Type:datetime_immutable)\'');
                $this->addSql('ALTER TABLE log_record ADD CONSTRAINT FK_8ECECC333DAC0075 FOREIGN KEY (process_execution_id) REFERENCES process_execution (id) ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE');
            }
            if (!$schema->hasTable('process_user')) {
                $this->addSql('CREATE SEQUENCE process_user_id_seq INCREMENT BY 1 MINVALUE 1 START 1');
                $this->addSql('CREATE TABLE process_user (id INT NOT NULL, email VARCHAR(255) NOT NULL, firstname VARCHAR(255) DEFAULT NULL, lastname VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, password VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id))');
                $this->addSql('CREATE UNIQUE INDEX UNIQ_627A047CE7927C74 ON process_user (email)');
                $this->addSql('CREATE INDEX idx_process_user_email ON process_user (email)');
            }
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE log_record DROP CONSTRAINT FK_8ECECC333DAC0075');
        $this->addSql('DROP TABLE log_record');
        $this->addSql('DROP TABLE process_execution');
        $this->addSql('DROP TABLE process_user');
    }
}
