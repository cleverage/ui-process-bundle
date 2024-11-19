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

namespace CleverAge\ProcessUiBundle\Controller\Admin\Process;

use CleverAge\ProcessUiBundle\Manager\ProcessConfigurationsManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/process/list', name: 'process_list')]
#[IsGranted('ROLE_USER')]
class ListAction extends AbstractController
{
    public function __invoke(ProcessConfigurationsManager $processConfigurationsManager): Response
    {
        return $this->render(
            '@CleverAgeProcessUi/admin/process/list.html.twig',
            [
                'processes' => $processConfigurationsManager->getPublicProcesses(),
            ]
        );
    }
}
