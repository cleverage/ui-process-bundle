<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Controller\Admin;

use CleverAge\ProcessUiBundle\Admin\Field\EnumField;
use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProcessExecutionCrudController extends AbstractCrudController
{
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
            TextField::new('source')->setTemplatePath('@CleverAgeProcessUi/admin/field/process_source.html.twig'),
            TextField::new('target')->setTemplatePath('@CleverAgeProcessUi/admin/field/process_target.html.twig'),
            TextField::new('duration')->formatValue(function($value, ProcessExecution $entity) {
                return $entity->duration(); // returned format can be changed here
            }),
            ArrayField::new('report')->setTemplatePath('@CleverAgeProcessUi/admin/field/report.html.twig'),
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
                        'processExecution' => [
                            'comparison' => '=',
                            'value' => $this->getContext()->getEntity()->getInstance()->getId(),
                        ],
                    ]
                )
                ->generateUrl();

        return $this->redirect($url);
    }

    public function downloadLogFile(
        AdminContext $context,
        #[Autowire(param: 'kernel.logs_dir')] string $directory,
    ): Response {
        /** @var ProcessExecution $processExecution */
        $processExecution = $context->getEntity()->getInstance();
        $filepath = $directory.DIRECTORY_SEPARATOR.$processExecution->code.DIRECTORY_SEPARATOR
            .$processExecution->logFilename;
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
        return $filters->add('code')->add('startDate');
    }
}
