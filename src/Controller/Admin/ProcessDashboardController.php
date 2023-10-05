<?php

declare(strict_types=1);

namespace CleverAge\ProcessUiBundle\Controller\Admin;

use CleverAge\ProcessUiBundle\Entity\LogRecord;
use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use CleverAge\ProcessUiBundle\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
class ProcessDashboardController extends AbstractDashboardController
{
    public function __construct(private readonly string $logoPath = '')
    {
    }

    #[Route('/process', name: 'process')]
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator->setController(ProcessExecutionCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('<img src="'.$this->logoPath.'" />');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::subMenu('Process', 'fas fa-gear')->setSubItems(
            [
                MenuItem::linkToRoute('Process list', 'fas fa-list', 'process_list'),
                MenuItem::linkToCrud('Executions', 'fas fa-rocket', ProcessExecution::class),
                MenuItem::linkToCrud('Logs', 'fas fa-pen', LogRecord::class),
            ]
        );
        if ($this->isGranted('ROLE_ADMIN')) {
            yield MenuItem::subMenu('Users', 'fas fa-user')->setSubItems(
                [
                    MenuItem::linkToCrud('User List', 'fas fa-user', User::class),
                ]
            );
        }
    }
}
