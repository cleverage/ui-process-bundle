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

namespace CleverAge\UiProcessBundle\Controller\Admin;

use CleverAge\ProcessBundle\Configuration\ProcessConfiguration;
use CleverAge\UiProcessBundle\Admin\Field\ContextField;
use CleverAge\UiProcessBundle\Admin\Field\LogLevelField;
use CleverAge\UiProcessBundle\Admin\Filter\LogProcessFilter;
use CleverAge\UiProcessBundle\Entity\LogRecord;
use CleverAge\UiProcessBundle\Manager\ProcessConfigurationsManager;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Monolog\Level;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class LogRecordCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ProcessConfigurationsManager $processConfigurationsManager,
        private readonly RequestStack $requestStack,
        private readonly RoleHierarchy $roleHierarchy,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return LogRecord::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            LogLevelField::new('level'),
            TextField::new('message')->setMaxLength(512),
            DateTimeField::new('createdAt')->setFormat('Y/M/dd H:mm:ss'),
            ContextField::new('context')
                ->onlyOnDetail(),
            BooleanField::new('contextIsEmpty', 'Has context info ?')
                ->onlyOnIndex()
                ->renderAsSwitch(false),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud->showEntityActionsInlined()->setPaginatorPageSize(250);
    }

    public function configureActions(Actions $actions): Actions
    {
        return Actions::new()
            ->add(Crud::PAGE_INDEX, Action::new('detail', false, 'fas fa-eye')
                ->setHtmlAttributes(
                    [
                        'data-bs-toggle' => 'tooltip',
                        'data-bs-placement' => 'top',
                        'title' => 'Show details',
                    ]
                )
                ->linkToCrudAction('detail'))
            ->add(Crud::PAGE_DETAIL, 'index');
    }

    public function configureFilters(Filters $filters): Filters
    {
        $id = $this->requestStack->getMainRequest()?->query->all('filters')['process']['value'] ?? null;
        $roles = $this->roleHierarchy->getReachableRoleNames($this->getUser()?->getRoles() ?? []);
        $processList = $this->processConfigurationsManager->getPublicProcesses();
        $processList = array_map(fn (ProcessConfiguration $cfg) => $cfg->getCode(), $processList);
        $processList = array_filter(
            $processList, fn (string $code) => \in_array('ROLE_PROCESS_VIEW#'.$code,
                $roles
            )
        );

        return $filters->add(
            LogProcessFilter::new('process', $processList, $id)
        )->add(
            ChoiceFilter::new('level')->setChoices(array_combine(Level::NAMES, Level::VALUES))
        )->add('message')->add('context')->add('createdAt');
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $roles = $this->roleHierarchy->getReachableRoleNames($this->getUser()?->getRoles() ?? []);
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $qb->join('entity.processExecution', 'processExecution');
        $qb->andWhere(
            $qb->expr()->in(
                (string) $qb->expr()->concat($qb->expr()->literal('ROLE_PROCESS_VIEW#'), 'processExecution.code'),
                ':roles'
            )
        )->setParameter('roles', $roles);

        return $qb;
    }
}
