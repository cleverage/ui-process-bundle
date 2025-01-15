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
        if (\in_array($filterDataDto->getComparison(), ['=', '>', '>=', '<', '<=', '!='])) {
            $queryBuilder->andWhere(
                \sprintf(
                    'entity.endDate %s date_add(entity.startDate, %s, \'SECOND\')',
                    $filterDataDto->getComparison(),
                    $filterDataDto->getValue()
                )
            );
        } elseif ('between' === $filterDataDto->getComparison()) {
            $queryBuilder->andWhere(
                \sprintf(
                    'entity.endDate BETWEEN date_add(entity.startDate, %s, \'SECOND\') and date_add(entity.startDate, %s, \'SECOND\')',
                    $filterDataDto->getValue(),
                    $filterDataDto->getValue2()
                )
            );
        }
    }
}
