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

namespace CleverAge\UiProcessBundle\Tests\App;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;

/**
 * An empty database for a test: the schema of the entities, created from their mapping.
 */
final class DatabaseSchema
{
    public static function recreate(EntityManagerInterface $entityManager): void
    {
        // Doctrine ORM 3.8+ with DBAL 4.5+: not using the schema editor API is deprecated. Only for the schema of the
        // tests: the migrations job validates the schema of the migrations without it (TestKernel)
        $configuration = $entityManager->getConfiguration();
        // @phpstan-ignore function.alreadyNarrowedType, function.alreadyNarrowedType (lowest dependencies: ORM 2, DBAL 3)
        if (method_exists($configuration, 'setUseSchemaEditorApi') && method_exists(Schema::class, 'edit')) {
            $configuration->setUseSchemaEditorApi(true);
        }

        $schemaTool = new SchemaTool($entityManager);
        // dropDatabase() rather than dropSchema(): with Doctrine ORM 3.8, dropSchema() calls the deprecated
        // Schema::dropTable() of DBAL 4.5+ for the tables that do not exist yet
        $schemaTool->dropDatabase();
        $schemaTool->createSchema($entityManager->getMetadataFactory()->getAllMetadata());
    }
}
