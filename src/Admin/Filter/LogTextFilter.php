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

namespace CleverAge\UiProcessBundle\Admin\Filter;

use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\TextFilterType;

/**
 * The TextFilter of EasyAdmin, for the message and the JSON context of the log records. On PostgreSQL, the column is
 * converted to text (no LIKE operator for the json type: "operator does not exist: json ~~ unknown", json || text is
 * text) and the comparison is case-insensitive, as with the default collations of MySQL and MariaDB (LIKE is
 * case-sensitive on PostgreSQL).
 *
 * @internal
 */
class LogTextFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(TextFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $column = \sprintf('%s.%s', $filterDataDto->getEntityAlias(), $filterDataDto->getProperty());
        $parameter = ':'.$filterDataDto->getParameterName();
        if ($queryBuilder->getEntityManager()->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $column = \sprintf("LOWER(CONCAT(%s, ''))", $column);
            $parameter = \sprintf('LOWER(%s)', $parameter);
        }

        $queryBuilder
            ->andWhere(\sprintf('%s %s %s', $column, $filterDataDto->getComparison(), $parameter))
            ->setParameter($filterDataDto->getParameterName(), $filterDataDto->getValue());
    }
}
