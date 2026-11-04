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
 * Add the idx_process_execution_code_start_date index: the last execution of each process of the process list.
 */
final class Version20261104120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add the idx_process_execution_code_start_date index';
    }

    public function up(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform || $platform instanceof PostgreSQLPlatform) {
            $this->addSql('CREATE INDEX idx_process_execution_code_start_date ON process_execution (code, start_date)');
        }
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform) {
            $this->addSql('DROP INDEX idx_process_execution_code_start_date ON process_execution');
        }
        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('DROP INDEX idx_process_execution_code_start_date');
        }
    }
}
