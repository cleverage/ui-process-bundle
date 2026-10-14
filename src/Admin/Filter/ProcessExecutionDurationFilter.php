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

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\NumericFilterType;

class ProcessExecutionDurationFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(string $propertyName, ?string $label = null): self
    {
        return (new self())
            ->setFilterFqcn(self::class)
            ->setProperty($propertyName)
            ->setLabel($label)
            ->setFormType(NumericFilterType::class)
            ->setFormTypeOption('translation_domain', 'EasyAdminBundle');
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        if (\in_array($filterDataDto->getComparison(), ['=', '>', '>=', '<', '<=', '!='], true)) {
            $queryBuilder->andWhere(
                \sprintf(
                    'entity.endDate %s date_add(entity.startDate, %s, \'SECOND\')',
                    $filterDataDto->getComparison(),
                    $this->toSeconds($filterDataDto->getValue())
                )
            );
        } elseif ('between' === $filterDataDto->getComparison()) {
            $queryBuilder->andWhere(
                \sprintf(
                    'entity.endDate BETWEEN date_add(entity.startDate, %s, \'SECOND\') and date_add(entity.startDate, %s, \'SECOND\')',
                    $this->toSeconds($filterDataDto->getValue()),
                    $this->toSeconds($filterDataDto->getValue2())
                )
            );
        }
    }

    /**
     * The number of seconds is cast to an integer and inlined in the DQL instead of being bound as a parameter: with
     * DBAL 3, SqlitePlatform generates the SECOND intervals inside a string literal (DATETIME(date, '+? SECOND')), so
     * a bound parameter is never bound ("column index out of range").
     */
    private function toSeconds(mixed $value): string
    {
        $seconds = is_numeric($value) ? (int) $value : 0;

        // A negative number is not a DQL literal
        return $seconds < 0 ? \sprintf('(%d)', $seconds) : (string) $seconds;
    }
}
