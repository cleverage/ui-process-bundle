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
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240729151928 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create table process_schedule';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE TABLE process_schedule (id INT AUTO_INCREMENT NOT NULL, process VARCHAR(255) NOT NULL, type VARCHAR(6) NOT NULL, expression VARCHAR(255) NOT NULL, input VARCHAR(255), context JSON NOT NULL, PRIMARY KEY(id))');
        }

        if ($platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform) {
            $this->addSql('CREATE TABLE process_schedule (id INT  AUTO_INCREMENT NOT NULL, process VARCHAR(255) NOT NULL, type VARCHAR(6) NOT NULL, expression VARCHAR(255) NOT NULL,  input VARCHAR(255), context JSON NOT NULL COMMENT \'(DC2Type:json)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB;');
        }
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE process_schedule');
    }
}
