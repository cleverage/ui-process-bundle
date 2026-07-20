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

use CleverAge\UiProcessBundle\Entity\User;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\LocaleSwitcher;

#[IsGranted('ROLE_USER')]
#[AdminDashboard(routePath: '/process', routeName: 'process')]
class ProcessDashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly LocaleSwitcher $localeSwitcher,
        private readonly string $logoPath = '',
    ) {
    }

    #[\Override]
    public function index(): Response
    {
        $adminUrlGenerator = $this->container->get(AdminUrlGenerator::class);

        return $this->redirect($adminUrlGenerator->setController(ProcessExecutionCrudController::class)->generateUrl());
    }

    #[\Override]
    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->renderContentMaximized()
            ->setTitle('<img src="'.$this->logoPath.'" />');
    }

    #[\Override]
    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Dashboard', 'fa fa-home');
        yield MenuItem::subMenu('Process', 'fas fa-gear')->setSubItems(
            [
                MenuItem::linkToRoute('Process list', 'fas fa-list', 'process_list'),
                MenuItem::linkTo(ProcessExecutionCrudController::class, 'Executions', 'fas fa-rocket'),
                MenuItem::linkTo(LogRecordCrudController::class, 'Logs', 'fas fa-pen'),
                MenuItem::linkTo(ProcessScheduleCrudController::class, 'Scheduler', 'fas fa-solid fa-clock'),
            ]
        );
        if ($this->isGranted('ROLE_ADMIN')) {
            yield MenuItem::subMenu('Users', 'fas fa-user')->setSubItems(
                [
                    MenuItem::linkTo(UserCrudController::class, 'User List', 'fas fa-user'),
                ]
            );
        }
    }

    #[\Override]
    public function configureCrud(): Crud
    {
        /** @var ?User $user */
        $user = $this->getUser();
        if (null !== $user?->getLocale()) {
            $this->localeSwitcher->setLocale($user->getLocale());
        }

        return parent::configureCrud()->setTimezone($user?->getTimezone() ?? date_default_timezone_get());
    }
}
