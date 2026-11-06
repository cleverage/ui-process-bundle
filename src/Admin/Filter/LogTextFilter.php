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

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\TextFilterType;

/**
 * The TextFilter of EasyAdmin, for the message and the JSON context of the log records, case-insensitive on every
 * database: the column is converted to text (PostgreSQL has no LIKE operator for the json type: "operator does not
 * exist: json ~~ unknown", json || text is text) and lowercased, as the value. LIKE is case-sensitive on PostgreSQL, and
 * on the JSON columns of MySQL and MariaDB (binary collation). On the JSON context, the value is also searched as encoded
 * by Doctrine (see encodeAsStored()).
 *
 * @internal
 */
class LogTextFilter implements FilterInterface
{
    use FilterTrait;

    /** Escape character of the LIKE patterns of the JSON representation of the value, instead of the backslash */
    private const LIKE_ESCAPE = '!';

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
        $column = \sprintf("LOWER(CONCAT(%s.%s, ''))", $filterDataDto->getEntityAlias(), $filterDataDto->getProperty());
        $comparison = $filterDataDto->getComparison();
        $parameterName = $filterDataDto->getParameterName();
        $value = $filterDataDto->getValue();
        $condition = \sprintf('%s %s LOWER(:%s)', $column, $comparison, $parameterName);
        $encodedValue = $this->isJson($entityDto, $filterDataDto->getProperty()) && \is_string($value)
            ? $this->encodeAsStored($value, $comparison)
            : null;
        if (null === $encodedValue) {
            $queryBuilder->andWhere($condition)->setParameter($parameterName, $value);

            return;
        }
        $encodedCondition = \sprintf(
            '%s %s LOWER(:%s_json)%s',
            $column,
            $comparison,
            $parameterName,
            $this->isLike($comparison) ? " ESCAPE '".self::LIKE_ESCAPE."'" : ''
        );
        // Matching either representation, or none of them for a negative comparison
        $queryBuilder
            ->andWhere(\sprintf(
                $this->isNegative($comparison) ? '(%s AND %s)' : '(%s OR %s)',
                $condition,
                $encodedCondition
            ))
            ->setParameter($parameterName, $value)
            ->setParameter($parameterName.'_json', $encodedValue);
    }

    /**
     * The value as written in the JSON column by Doctrine (json_encode() of the DBAL "json" type: "/" written "\/",
     * non-ASCII characters "\uXXXX"...), kept as it is by PostgreSQL (json type), MariaDB and SQLite (text): a search
     * with such characters found nothing there. MySQL stores the JSON in a binary format, its text representation
     * does not escape them. Null when the value is written the same.
     */
    private function encodeAsStored(string $value, string $comparison): ?string
    {
        $encoded = json_encode($value, \JSON_PRESERVE_ZERO_FRACTION);
        if (false === $encoded) {
            return null;
        }
        $encoded = substr($encoded, 1, -1);
        if ($encoded === $value) {
            return null;
        }

        // Escaped with its own character: the backslashes are then literal in the pattern (escape characters by default
        // on MySQL, MariaDB and PostgreSQL, not on SQLite)
        return $this->isLike($comparison) ? str_replace(self::LIKE_ESCAPE, self::LIKE_ESCAPE.self::LIKE_ESCAPE, $encoded) : $encoded;
    }

    /**
     * @param EntityDto<object> $entityDto
     */
    private function isJson(EntityDto $entityDto, string $property): bool
    {
        $classMetadata = $entityDto->getClassMetadata();

        return $classMetadata->hasField($property) && Types::JSON === $classMetadata->getTypeOfField($property);
    }

    private function isLike(string $comparison): bool
    {
        return str_contains(strtolower($comparison), 'like');
    }

    private function isNegative(string $comparison): bool
    {
        return str_starts_with(strtolower($comparison), 'not') || '!=' === $comparison || '<>' === $comparison;
    }
}
