<?php

namespace CleverAge\ProcessUiBundle\Controller;

use CleverAge\ProcessUiBundle\Controller\Crud\ProcessCrudController;
use CleverAge\ProcessUiBundle\Entity\Process;
use CleverAge\ProcessUiBundle\Entity\ProcessExecution;
use CleverAge\ProcessUiBundle\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardController extends AbstractDashboardController
{
    #[Route('/', name: 'process_admin')]
    public function index(): Response
    {
        /** @var AdminUrlGenerator $routeBuilder */
        $routeBuilder = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($routeBuilder->setController(ProcessCrudController::class)->generateUrl());
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()->setTitle('CleverAge Process UI');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::section('Process', 'fas fa-tasks')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('List', 'fa fa-list', Process::class)->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('History', 'fa fa-history', ProcessExecution::class)->setPermission('ROLE_ADMIN');

        yield MenuItem::section();
        yield MenuItem::section('Settings', 'fas fa-tools')->setPermission('ROLE_ADMIN');
        yield MenuItem::linkToCrud('Users', 'fa fa-users', User::class)->setPermission('ROLE_ADMIN');
    }
}
