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

namespace CleverAge\ProcessUiBundle\Controller\Crud;

use CleverAge\ProcessUiBundle\Entity\Process;
use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use CleverAge\ProcessUiBundle\Manager\ProcessUiConfigurationManager;
use CleverAge\ProcessUiBundle\Message\ProcessRunMessage;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\SortOrder;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Form\Type\ComparisonType;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\MessageBusInterface;

class ProcessCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ProcessUiConfigurationManager $processUiConfigurationManager,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Process::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $crud->showEntityActionsInlined();
        $crud->setDefaultSort(['lastExecutionDate' => SortOrder::DESC]);
        $crud->setEntityPermission('ROLE_ADMIN');
        $crud->setSearchFields(['processCode', 'source', 'target']);

        return $crud;
    }

    /**
     * @return array <int, string|Field|IntegerField>
     */
    public function configureFields(string $pageName): array
    {
        return [
            Field::new('processCode', 'Process'),
            'source',
            'target',
            'lastExecutionDate',
            IntegerField::new('lastExecutionStatus')->formatValue(static fn (?int $value) => match ($value) {
                ProcessExecution::STATUS_FAIL => '<button class="btn btn-danger btn-lm">failed</button>',
                ProcessExecution::STATUS_START => '<button class="btn btn-warning btn-lm">started</button>',
                ProcessExecution::STATUS_SUCCESS => '<button class="btn btn-success btn-lm">success</button>',
                default => '<button class="btn btn-info btn-lm">unknown</button>',
            }),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions->remove(Crud::PAGE_INDEX, Action::EDIT);
        $actions->remove(Crud::PAGE_INDEX, Action::DELETE);
        $actions->remove(Crud::PAGE_INDEX, Action::NEW);
        $runProcess = Action::new('run', '', 'fa fa-rocket')
            ->linkToCrudAction('runProcessAction');
        $runProcess->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Run process in background']);
        $runProcess->displayIf(fn (Process $process) => $this->processUiConfigurationManager->canRun($process));
        $viewHistoryAction = Action::new('viewHistory', '', 'fa fa-history')
            ->linkToCrudAction('viewHistoryAction');
        $viewHistoryAction->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'View executions history']);
        $actions->add(Crud::PAGE_INDEX, $viewHistoryAction);
        $actions->add(Crud::PAGE_INDEX, $runProcess);

        return $actions;
    }

    public function runProcess(AdminContext $context): Response
    {
        try {
            /** @var Process $process */
            $process = $context->getEntity()->getInstance();
            if (false === $this->processUiConfigurationManager->canRun($process)) {
                $this->addFlash(
                    'warning',
                    'Process is not run-able via Ui.'
                );
            } else {
                $message = new ProcessRunMessage($process->getProcessCode());
                $this->messageBus->dispatch($message);
                $this->addFlash(
                    'success',
                    'Process has been added to queue. It will start as soon as possible'
                );
            }
        } catch (\Exception) {
            $this->addFlash('warning', 'Cannot run process.');
        }

        return $this->redirect(
            $this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl()
        );
    }

    public function viewHistory(AdminContext $adminContext): RedirectResponse
    {
        /** @var Process $process */
        $process = $adminContext->getEntity()->getInstance();

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(ProcessExecutionCrudController::class)
                ->setEntityId(null)
                ->setAction(Action::INDEX)
                ->setAll([
                    'filters' => [
                        'processCode' => ['comparison' => ComparisonType::EQ, 'value' => $process->getProcessCode()],
                    ],
                ])
                ->generateUrl()
        );
    }
}
