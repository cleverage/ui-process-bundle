<?php

namespace CleverAge\ProcessUiBundle\Controller\Crud;

use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use CleverAge\ProcessUiBundle\Manager\ProcessUiConfigurationManager;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\SortOrder;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProcessExecutionCrudController extends AbstractCrudController
{
    private bool $indexLogs;
    private string $processLogDir;
    private ProcessUiConfigurationManager $processUiConfigurationManager;

    /**
     * @required
     */
    public function setIndexLogs(bool $indexLogs): void
    {
        $this->indexLogs = $indexLogs;
    }

    /**
     * @required
     */
    public function setProcessLogDir(string $processLogDir): void
    {
        $this->processLogDir = $processLogDir;
    }

    /**
     * @required true
     */
    public function setProcessUiConfigurationManager(ProcessUiConfigurationManager $processUiConfigurationManager): void
    {
        $this->processUiConfigurationManager = $processUiConfigurationManager;
    }

    public static function getEntityFqcn(): string
    {
        return ProcessExecution::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        $crud->showEntityActionsAsDropdown(false);
        $crud->setDefaultSort(['startDate' => SortOrder::DESC]);
        $crud->setEntityPermission('ROLE_ADMIN');
        $crud->setSearchFields($this->indexLogs === true ? ['logRecords.message'] : null);

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
            'startDate',
            'endDate',
            IntegerField::new('status')->formatValue(static function (int $value) {
                /** @phpstan-ignore-next-line */
                return match ($value) {
                    ProcessExecution::STATUS_FAIL => '<button class="btn btn-danger btn-lm">failed</button>',
                    ProcessExecution::STATUS_START => '<button class="btn btn-warning btn-lm">started</button>',
                    ProcessExecution::STATUS_SUCCESS => '<button class="btn btn-success btn-lm">succcess</button>',
                };
            }),
        ];
    }

    public function configureFilters(Filters $filters): Filters
    {
        $processCodeChoices = $this->processUiConfigurationManager->getProcessChoices();
        if (count($processCodeChoices) > 0) {
            $filters->add(ChoiceFilter::new('processCode', 'Process')->setChoices($processCodeChoices));
        }

        $sourceChoices = $this->processUiConfigurationManager->getSourceChoices();
        if (count($sourceChoices) > 0) {
            $filters->add(ChoiceFilter::new('source')->setChoices($sourceChoices));
        }

        $targetChoices = $this->processUiConfigurationManager->getTargetChoices();
        if (count($targetChoices) > 0) {
            $filters->add(ChoiceFilter::new('target')->setChoices($targetChoices));
        }
        $filters->add(ChoiceFilter::new('status')->setChoices([
            'failed' => ProcessExecution::STATUS_FAIL,
            'success' => ProcessExecution::STATUS_SUCCESS,
            'started' => ProcessExecution::STATUS_START,
        ]));
        $filters->add('startDate');
        $filters->add('endDate');

        return $filters;
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions->remove(Crud::PAGE_INDEX, Action::EDIT);
        $actions->remove(Crud::PAGE_INDEX, Action::DELETE);
        $actions->remove(Crud::PAGE_INDEX, Action::NEW);

        $downloadLogAction = Action::new('downloadLog', '', 'fa fa-file-download')
            ->linkToCrudAction('downloadLog');
        $downloadLogAction->setHtmlAttributes(['data-toggle' => 'tooltip', 'title' => 'Download log file']);
        $actions->add(Crud::PAGE_INDEX, $downloadLogAction);

        return $actions;
    }

    public function downloadLog(AdminContext $context): Response
    {
        /** @var ProcessExecution $processExecution */
        $processExecution = $context->getEntity()->getInstance();
        $filepath = $this->processLogDir . DIRECTORY_SEPARATOR . $processExecution->getLog();
        $basename = basename($filepath);
        $content = file_get_contents($filepath);
        if (false === $content) {
            throw new NotFoundHttpException("Log file not found.");
        }
        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/plain; charset=utf-8');
        $response->headers->set('Content-Disposition', "attachment; filename=\"$basename\"");

        return $response;
    }
}
