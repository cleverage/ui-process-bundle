<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20231006111525 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        if (!$schema->hasTable('log_record')) {
            $this->addSql(
                <<<SQL
                CREATE TABLE log_record (
                    id INT NOT NULL, 
                    process_execution_id INT DEFAULT NULL, 
                    channel VARCHAR(64) NOT NULL, 
                    level INT NOT NULL, 
                    message VARCHAR(512) NOT NULL, 
                    context JSON NOT NULL, 
                    created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(id)
                )
            SQL
            );
            $this->addSql('CREATE INDEX IDX_8ECECC333DAC0075 ON log_record (process_execution_id)');
            $this->addSql('CREATE INDEX idx_log_record_level ON log_record (level)');
            $this->addSql('CREATE INDEX idx_log_record_created_at ON log_record (created_at)');
        }

        if (!$schema->hasTable('process_execution')) {
            $this->addSql(<<<SQL
                CREATE TABLE process_execution (
                    id INT NOT NULL, 
                    code VARCHAR(255) NOT NULL, 
                    log_filename VARCHAR(255) NOT NULL, 
                    start_date TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, 
                    end_date TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, 
                    status VARCHAR(255) NOT NULL, 
                    report JSON NOT NULL, PRIMARY KEY(id)
                )
            SQL);
            $this->addSql(
                <<<SQL
                ALTER TABLE log_record 
                    ADD CONSTRAINT FK_8ECECC333DAC0075 
                    FOREIGN KEY (process_execution_id) 
                    REFERENCES process_execution (id) 
                    ON DELETE CASCADE NOT DEFERRABLE INITIALLY IMMEDIATE
            SQL
            );
            $this->addSql('CREATE INDEX idx_process_execution_code ON process_execution (code)');
            $this->addSql('CREATE INDEX idx_process_execution_start_date ON process_execution (start_date)');
        }

        if (!$schema->hasTable('process_user')) {
            $this->addSql(<<<SQL
                CREATE TABLE process_user (
                    id INT NOT NULL, 
                    email VARCHAR(255) NOT NULL, 
                    firstname VARCHAR(255) DEFAULT NULL, 
                    lastname VARCHAR(255) DEFAULT NULL, 
                    roles JSON NOT NULL, 
                    password VARCHAR(255) DEFAULT NULL, PRIMARY KEY(id)
                )
            SQL);
            $this->addSql('CREATE UNIQUE INDEX UNIQ_627A047CE7927C74 ON process_user (email)');
            $this->addSql('CREATE INDEX idx_process_user_email ON process_user (email)');
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
