<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Admin\Filter;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\ProcessUiBundle\Manager\ProcessConfigurationsManager;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Filter\FilterInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FieldDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\FilterDataDto;
use EasyCorp\Bundle\EasyAdminBundle\Filter\FilterTrait;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;

class LogProcessCodeFilter implements FilterInterface
{
    use FilterTrait;

    private ?int $currentProcessExecutionId = null;

    public static function new($label = null): self
    {
        return (new self())
            ->setFilterFqcn(__CLASS__)
            ->setProperty('processExecution')
            ->setLabel($label)
            ->setFormType(ChoiceType::class);
    }

    public function addChoices(ProcessConfigurationsManager $manager): self
    {
        $choices = $manager->getPublicProcesses();
        $choices = array_map(fn (ProcessConfiguration $cfg) => $cfg->getCode(), $choices);
        $this->setFormTypeOption('choices', $choices);

        return $this;
    }

    public function setCurrentProcessExecutionId(?int $currentProcessExecutionId): self
    {
        $this->currentProcessExecutionId = $currentProcessExecutionId;
        if (0 !== $this->currentProcessExecutionId) {
            $this->setFormTypeOption('disabled', true);
        }

        return $this;
    }

    public function apply(QueryBuilder $queryBuilder, FilterDataDto $filterDataDto, ?FieldDto $fieldDto, EntityDto $entityDto): void
    {
        $queryBuilder->join('entity.processExecution', 'pe');
        if (null !== $this->currentProcessExecutionId) {
            $queryBuilder->andWhere($queryBuilder->expr()->eq('pe.id', ':id'));
            $queryBuilder->setParameter('id', $this->currentProcessExecutionId);

            return;
        }
        $queryBuilder->where('pe.code IN (:codes)');
        $queryBuilder->setParameter('codes', $filterDataDto->getValue());
    }
}
