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
 * Text filter on a JSON column, the TextFilter of EasyAdmin: PostgreSQL has no LIKE operator for the json type
 * ("operator does not exist: json ~~ unknown"), the column is converted to text there (json || text is text).
 *
 * @internal
 */
class LogContextFilter implements FilterInterface
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
        if ($queryBuilder->getEntityManager()->getConnection()->getDatabasePlatform() instanceof PostgreSQLPlatform) {
            $column = \sprintf("CONCAT(%s, '')", $column);
        }

        $queryBuilder
            ->andWhere(\sprintf('%s %s :%s', $column, $filterDataDto->getComparison(), $filterDataDto->getParameterName()))
            ->setParameter($filterDataDto->getParameterName(), $filterDataDto->getValue());
    }
}
