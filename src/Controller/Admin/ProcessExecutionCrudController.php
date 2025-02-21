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

use CleverAge\UiProcessBundle\Admin\Field\ContextField;
use CleverAge\UiProcessBundle\Admin\Field\EnumField;
use CleverAge\UiProcessBundle\Admin\Filter\ProcessExecutionDurationFilter;
use CleverAge\UiProcessBundle\Entity\ProcessExecution;
use CleverAge\UiProcessBundle\Repository\ProcessExecutionRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Core\Role\RoleHierarchy;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ProcessExecutionCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ProcessExecutionRepository $processExecutionRepository,
        private readonly string $logDirectory,
        private readonly RoleHierarchy $roleHierarchy,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return ProcessExecution::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('code'),
            EnumField::new('status'),
            DateTimeField::new('startDate')->setFormat('Y/M/dd H:mm:ss'),
            DateTimeField::new('endDate')->setFormat('Y/M/dd H:mm:ss'),
            TextField::new('source')->setTemplatePath('@CleverAgeUiProcess/admin/field/process_source.html.twig'),
            TextField::new('target')->setTemplatePath('@CleverAgeUiProcess/admin/field/process_target.html.twig'),
            TextField::new('duration')->formatValue(function ($value, ProcessExecution $entity) {
                return $entity->duration(); // returned format can be changed here
            }),
            ArrayField::new('report')->setTemplatePath('@CleverAgeUiProcess/admin/field/report.html.twig'),
            ContextField::new('context'),
        ];
    }

    public function configureCrud(Crud $crud): Crud
    {
        $crud->showEntityActionsInlined();
        $crud->setDefaultSort(['startDate' => 'DESC']);

        return $crud;
    }

    public function configureActions(Actions $actions): Actions
    {
        return Actions::new()
            ->add(
                Crud::PAGE_INDEX,
                Action::new('showLogs', false, 'fas fa-eye')
                    ->setHtmlAttributes(
                        [
                            'data-bs-toggle' => 'tooltip',
                            'data-bs-placement' => 'top',
                            'title' => 'Show logs stored in database',
                        ]
                    )
                    ->linkToCrudAction('showLogs')
                    ->displayIf(fn (ProcessExecution $entity) => $this->processExecutionRepository->hasLogs($entity))
            )->add(
                Crud::PAGE_INDEX,
                Action::new('downloadLogfile', false, 'fas fa-download')
                    ->setHtmlAttributes(
                        [
                            'data-bs-toggle' => 'tooltip',
                            'data-bs-placement' => 'top',
                            'title' => 'Download log file',
                        ]
                    )
                    ->linkToCrudAction('downloadLogFile')
                    ->displayIf(fn (ProcessExecution $entity) => file_exists($this->getLogFilePath($entity)))
            );
    }

    public function showLogs(AdminContext $adminContext): RedirectResponse
    {
        /** @var AdminUrlGenerator $adminUrlGenerator */
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);
        $url = $adminUrlGenerator
                ->setController(LogRecordCrudController::class)
                ->setAction('index')
                ->setEntityId(null)
                ->set(
                    'filters',
                    [
                        'process' => [
                            'comparison' => '=',
                            'value' => $this->getContext()?->getEntity()->getInstance()->getId(),
                        ],
                    ]
                )
                ->generateUrl();

        return $this->redirect($url);
    }

    public function downloadLogFile(
        AdminContext $context,
    ): Response {
        /** @var ProcessExecution $processExecution */
        $processExecution = $context->getEntity()->getInstance();
        $filepath = $this->getLogFilePath($processExecution);
        $basename = basename($filepath);
        $content = file_get_contents($filepath);
        if (false === $content) {
            throw new NotFoundHttpException('Log file not found.');
        }
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/plain; charset=utf-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"$basename\"");

        return $response;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('code')
            ->add('startDate')
            ->add(
                ProcessExecutionDurationFilter::new('duration', 'Duration (in seconds)')
                    ->setFormTypeOption('mapped', false)
            );
    }

    private function getLogFilePath(ProcessExecution $processExecution): string
    {
        return $this->logDirectory.
            \DIRECTORY_SEPARATOR.$processExecution->code.
            \DIRECTORY_SEPARATOR.$processExecution->logFilename
        ;
    }

    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters,
    ): QueryBuilder {
        $roles = $this->roleHierarchy->getReachableRoleNames($this->getUser()?->getRoles() ?? []);
        $qb = parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters);
        $qb->andWhere(
            $qb->expr()->in(
                (string) $qb->expr()->concat($qb->expr()->literal('ROLE_PROCESS_VIEW#'), 'entity.code'),
                ':roles'
            )
        )->setParameter('roles', $roles);

        return $qb;
    }
}
