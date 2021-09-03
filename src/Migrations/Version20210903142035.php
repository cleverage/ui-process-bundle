<?php

declare(strict_types=1);

namespace CleverAgeProcessUi;

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
        $this->addSql('CREATE TABLE process_execution (id INT AUTO_INCREMENT NOT NULL, process_code VARCHAR(255) DEFAULT NULL, source VARCHAR(255) DEFAULT NULL, target VARCHAR(255) DEFAULT NULL, start_date DATETIME NOT NULL, end_date DATETIME DEFAULT NULL, status INT NOT NULL, response VARCHAR(255) DEFAULT NULL, data VARCHAR(255) DEFAULT NULL, log VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE process_execution_log_record (id INT AUTO_INCREMENT NOT NULL, process_execution_id INT DEFAULT NULL, log_level INT NOT NULL, message VARCHAR(255) NOT NULL, INDEX IDX_F7C4B6683DAC0075 (process_execution_id), INDEX process_execution_log_message (message), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE user (id INT AUTO_INCREMENT NOT NULL, email VARCHAR(255) NOT NULL, firstname VARCHAR(255) DEFAULT NULL, lastname VARCHAR(255) DEFAULT NULL, roles JSON NOT NULL, password VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_8D93D649E7927C74 (email), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE messenger_messages (id BIGINT AUTO_INCREMENT NOT NULL, body LONGTEXT NOT NULL, headers LONGTEXT NOT NULL, queue_name VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, available_at DATETIME NOT NULL, delivered_at DATETIME DEFAULT NULL, INDEX IDX_75EA56E016BA31DB (delivered_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE process_execution_log_record ADD CONSTRAINT FK_F7C4B6683DAC0075 FOREIGN KEY (process_execution_id) REFERENCES process_execution (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE process_execution_log_record DROP FOREIGN KEY FK_F7C4B6683DAC0075');
        $this->addSql('DROP TABLE process_execution');
        $this->addSql('DROP TABLE process_execution_log_record');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE messenger_messages');
    }
}
