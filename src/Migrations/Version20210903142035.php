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

namespace CleverAge\ProcessUiBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210903142035 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<SQL
            CREATE TABLE process_execution
                (
                    id INT AUTO_INCREMENT NOT NULL,
                    process_code VARCHAR(255) DEFAULT NULL,
                    source VARCHAR(255) DEFAULT NULL,
                    target VARCHAR(255) DEFAULT NULL,
                    start_date DATETIME NOT NULL,
                    end_date DATETIME DEFAULT NULL,
                    status INT NOT NULL,
                    response VARCHAR(255) DEFAULT NULL,
                    data VARCHAR(255) DEFAULT NULL,
                    log VARCHAR(255) DEFAULT NULL,
                    PRIMARY KEY(id))
                    DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<SQL
            CREATE TABLE process_execution_log_record
                (
                    id INT AUTO_INCREMENT NOT NULL,
                    process_execution_id INT DEFAULT NULL,
                    log_level INT NOT NULL,
                    message VARCHAR(255) NOT NULL,
                    INDEX IDX_F7C4B6683DAC0075 (process_execution_id),
                    INDEX process_execution_log_message (message),
                    PRIMARY KEY(id))
                    DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);

        $this->addSql(<<<SQL
            CREATE TABLE user
                (
                    id INT AUTO_INCREMENT NOT NULL,
                    email VARCHAR(255) NOT NULL,
                    firstname VARCHAR(255) DEFAULT NULL,
                    lastname VARCHAR(255) DEFAULT NULL,
                    roles JSON NOT NULL,
                    password VARCHAR(255) NOT NULL,
                    UNIQUE INDEX UNIQ_8D93D649E7927C74 (email),
                    PRIMARY KEY(id))
                    DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql(<<<SQL
            ALTER TABLE process_execution_log_record
                ADD CONSTRAINT FK_F7C4B6683DAC0075
                    FOREIGN KEY (process_execution_id)
                        REFERENCES process_execution (id)
                        ON DELETE CASCADE
            SQL);
        $this->addSql(<<<SQL
            CREATE TABLE process
                (
                    id INT AUTO_INCREMENT NOT NULL,
                    process_code TINYTEXT NOT NULL,
                    source TINYTEXT DEFAULT NULL,
                    target TINYTEXT DEFAULT NULL,
                    last_execution_date DATETIME DEFAULT NULL,
                    last_execution_status INT DEFAULT NULL,
                    PRIMARY KEY(id))
                    DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
            SQL);
        $this->addSql('ALTER TABLE process_execution ADD process_id INT DEFAULT NULL');
        $this->addSql(<<<SQL
            ALTER TABLE process_execution
                ADD CONSTRAINT FK_98E995D27EC2F574
                    FOREIGN KEY (process_id) REFERENCES process (id) ON DELETE SET NULL
            SQL);
        $this->addSql('CREATE INDEX IDX_98E995D27EC2F574 ON process_execution (process_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE process');
        $this->addSql('DROP TABLE process_execution');
        $this->addSql('DROP TABLE process_execution_log_record');
        $this->addSql('DROP TABLE user');
    }

    public function isTransactional(): bool
    {
        return false;
    }
}
