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

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20241009075733 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add process_user.locale';
    }

    public function up(Schema $schema): void
    {
        if ($schema->hasTable('process_user') && !$schema->getTable('process_user')->hasColumn('locale')) {
            $this->addSql('ALTER TABLE process_user ADD locale VARCHAR(255) DEFAULT NULL');
        }
    }

    public function down(Schema $schema): void
    {
        if ($schema->hasTable('process_user') && $schema->getTable('process_user')->hasColumn('locale')) {
            $this->addSql('ALTER TABLE process_user DROP locale');
        }
    }
}
