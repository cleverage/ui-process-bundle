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
use Symfony\Component\Routing\Attribute\Route;

class DashboardController extends AbstractDashboardController
{
    #[Route('/', name: 'cleverage_ui_process_admin')]
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
