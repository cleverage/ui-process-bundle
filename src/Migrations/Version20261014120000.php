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
 * Add process_execution.stop_requested_at and process_execution.heartbeat_at. With DBAL 3 using the type comments,
 * the (DC2Type:datetime_immutable) comments are added.
 */
final class Version20261014120000 extends AbstractMigration
{
    #[\Override]
    public function getDescription(): string
    {
        return 'Add process_execution.stop_requested_at and process_execution.heartbeat_at';
    }

    public function up(Schema $schema): void
    {
        $typeComments = !$this->connection->getConfiguration()->getDisableTypeComments();
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform) {
            $comment = $typeComments ? ' COMMENT \'(DC2Type:datetime_immutable)\'' : '';
            $this->addSql('ALTER TABLE process_execution ADD stop_requested_at DATETIME DEFAULT NULL'.$comment.', ADD heartbeat_at DATETIME DEFAULT NULL'.$comment);
        }

        if ($platform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE process_execution ADD stop_requested_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL, ADD heartbeat_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
            if ($typeComments) {
                $this->addSql('COMMENT ON COLUMN process_execution.stop_requested_at IS \'(DC2Type:datetime_immutable)\'');
                $this->addSql('COMMENT ON COLUMN process_execution.heartbeat_at IS \'(DC2Type:datetime_immutable)\'');
            }
        }
    }

    #[\Override]
    public function down(Schema $schema): void
    {
        $platform = $this->connection->getDatabasePlatform();
        if ($platform instanceof MariaDBPlatform || $platform instanceof MySQLPlatform || $platform instanceof PostgreSQLPlatform) {
            $this->addSql('ALTER TABLE process_execution DROP stop_requested_at, DROP heartbeat_at');
        }
    }
}
