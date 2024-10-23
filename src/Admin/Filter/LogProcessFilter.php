<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Admin\Filter;

use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use EasyCorp\Bundle\EasyAdminBundle\Form\Filter\Type\ChoiceFilterType;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;

class LogProcessFilter implements FilterInterface
{
    use FilterTrait;

    public static function new(
        $label,
        array $choices,
        null|string|int $executionId = null,
    ): self
    {
        if (is_numeric($executionId)) {
            $choices = [$executionId => $executionId];
        }

        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty('process')
            ->setLabel($label)
            ->setFormType(ChoiceFilterType::class)
            ->setFormTypeOption('value_type_options', ['choices' => $choices])
            ->setFormTypeOption('data', ['comparison' => ComparisonType::EQ, 'value' => $executionId]);
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $value = $filterDataDto->getValue();
        $queryBuilder->join('entity.processExecution', 'pe');
        if (is_numeric($value)) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('pe.id', ':id'));
            $queryBuilder->setParameter('id', $value);

            return;
        }
        $queryBuilder->where('pe.code IN (:codes)');
        $queryBuilder->setParameter('codes', $filterDataDto->getValue());
    }
}
