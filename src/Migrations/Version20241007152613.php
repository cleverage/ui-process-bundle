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
final class Version20241007152613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add process_execution.context';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('process_execution') && !$schema->getTable('process_execution')->hasColumn('context')) {
            $this->addSql('ALTER TABLE process_execution ADD context JSON NOT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('process_execution') && $schema->getTable('process_execution')->hasColumn('context')) {
            $this->addSql('ALTER TABLE process_execution DROP context');
        }
    }
}
