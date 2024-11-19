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

namespace CleverAge\ProcessUiBundle\Controller\Admin\Security;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;

class LogoutController extends AbstractController
{
    #[\Symfony\Component\Routing\Attribute\Route('/process/logout', name: 'process_logout')]
    public function __invoke(Security $security): Response
    {
        $security->logout();

        return $this->redirectToRoute('process_login');
    }
}
